<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Filament\Resources\Activities\ActivityResource;
use App\Filament\Resources\Activities\Pages\ListActivities;
use App\Filament\Resources\Areas\AreaResource;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\UserResource;
use App\Models\Activity;
use App\Models\Area;
use App\Models\Borrower;
use App\Models\User;
use App\Services\LoanService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use LogicException;
use Tests\TestCase;

class AdministrationTest extends TestCase
{
    use RefreshDatabase;

    private Area $area;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->area = Area::create(['name' => 'North', 'code' => 'N']);
        $this->admin = User::create(['name' => 'Admin', 'email' => 'admin@test', 'password' => 'secret123', 'role' => UserRole::Admin]);
    }

    private function staff(): User
    {
        return User::create(['name' => 'Staff', 'email' => 'staff@test', 'password' => 'secret123', 'role' => UserRole::Staff, 'area_id' => $this->area->id]);
    }

    public function test_admin_pages_render(): void
    {
        $this->actingAs($this->admin);

        $this->get(AreaResource::getUrl('index'))->assertOk()->assertSee('North');
        $this->get(UserResource::getUrl('index'))->assertOk()->assertSee('admin@test');
        $this->get(UserResource::getUrl('create'))->assertOk();
        $this->get(ActivityResource::getUrl('index'))->assertOk();
    }

    public function test_staff_cannot_open_admin_pages(): void
    {
        $this->actingAs($this->staff());

        $this->get(AreaResource::getUrl('index'))->assertForbidden();
        $this->get(UserResource::getUrl('index'))->assertForbidden();
        $this->get(UserResource::getUrl('create'))->assertForbidden();
        $this->get(ActivityResource::getUrl('index'))->assertForbidden();
    }

    public function test_admin_creates_staff_and_switching_to_admin_clears_area(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(CreateUser::class)
            ->fillForm([
                'name' => 'New Staff',
                'email' => 'new@test',
                'password' => 'password123',
                'role' => 'staff',
                'area_id' => $this->area->id,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $user = User::firstWhere('email', 'new@test');
        $this->assertSame($this->area->id, $user->area_id);

        Livewire::test(EditUser::class, ['record' => $user->getRouteKey()])
            ->fillForm(['role' => 'admin'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertNull($user->refresh()->area_id);
        $this->assertTrue($user->isAdmin());
    }

    public function test_staff_requires_area(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(CreateUser::class)
            ->fillForm(['name' => 'X', 'email' => 'x@test', 'password' => 'password123', 'role' => 'staff'])
            ->call('create')
            ->assertHasFormErrors(['area_id' => 'required']);
    }

    public function test_staff_can_change_own_password(): void
    {
        $staff = $this->staff();
        $this->actingAs($staff);

        $this->get(Filament::getPanel('admin')->getProfileUrl())->assertOk();

        Livewire::test(\Filament\Auth\Pages\EditProfile::class)
            ->fillForm(['password' => 'new-password-123', 'passwordConfirmation' => 'new-password-123', 'currentPassword' => 'secret123'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('new-password-123', $staff->refresh()->password));
    }

    public function test_area_scope_applies_when_user_not_yet_resolved(): void
    {
        $staff = $this->staff();
        Borrower::create(['area_id' => Area::create(['name' => 'South', 'code' => 'S'])->id, 'first_name' => 'X', 'last_name' => 'Other']);
        Borrower::create(['area_id' => $this->area->id, 'first_name' => 'Y', 'last_name' => 'Mine']);

        // Simulate a request where the session holds the user but nothing has resolved it yet.
        auth()->guard('web')->setUser($staff);
        $this->assertSame(['Mine'], Borrower::pluck('last_name')->all());
    }

    public function test_inactive_user_cannot_access_panel(): void
    {
        $staff = $this->staff();
        $staff->update(['is_active' => false]);

        $this->assertFalse($staff->canAccessPanel(Filament::getPanel('admin')));
    }

    public function test_changes_are_audited_with_user_area_and_values(): void
    {
        $this->actingAs($staff = $this->staff());

        $borrower = Borrower::create(['first_name' => 'Juan', 'last_name' => 'Cruz']);
        $borrower->update(['contact_no' => '09171234567']);

        $entry = Activity::where('subject_type', Borrower::class)->where('event', 'updated')->latest('id')->first();

        $this->assertSame($staff->id, $entry->causer_id);
        $this->assertSame($this->area->id, $entry->area_id);
        $this->assertSame('09171234567', $entry->properties['attributes']['contact_no']);
        $this->assertArrayHasKey('contact_no', $entry->properties['old']);
    }

    public function test_payment_audit_excludes_derived_balance(): void
    {
        $this->actingAs($this->admin);
        $borrower = Borrower::create(['area_id' => $this->area->id, 'first_name' => 'Juan', 'last_name' => 'Cruz']);
        $service = app(LoanService::class);
        $loan = $service->create([
            'borrower_id' => $borrower->id, 'principal' => 1000, 'interest_rate' => 20, 'rate_basis' => 'per_term',
            'interest_method' => 'flat', 'payment_frequency' => 'daily', 'term' => 10, 'term_unit' => 'days', 'start_date' => today(),
        ]);
        $service->recordPayment($loan, ['amount' => 120, 'payment_date' => today()->toDateString(), 'receipt_no' => '0001']);

        $entry = Activity::where('subject_type', \App\Models\Payment::class)->firstOrFail();
        $this->assertSame('created', $entry->event);
        $this->assertArrayNotHasKey('balance_after', $entry->properties['attributes']);
    }

    public function test_login_and_failed_login_are_audited(): void
    {
        $staff = $this->staff();

        auth()->attempt(['email' => 'staff@test', 'password' => 'wrong']);
        auth()->attempt(['email' => 'staff@test', 'password' => 'secret123']);

        $this->assertTrue(Activity::where('event', 'login_failed')->exists());

        $login = Activity::where('event', 'login')->firstOrFail();
        $this->assertSame($staff->id, $login->causer_id);
        $this->assertSame($this->area->id, $login->area_id);
    }

    public function test_audit_entries_cannot_be_edited_or_deleted(): void
    {
        $this->actingAs($this->admin);
        Borrower::create(['area_id' => $this->area->id, 'first_name' => 'Juan', 'last_name' => 'Cruz']);
        $entry = Activity::firstOrFail();

        try {
            $entry->update(['description' => 'tampered']);
            $this->fail('Audit entry was edited.');
        } catch (LogicException) {
        }

        $this->expectException(LogicException::class);
        $entry->delete();
    }

    public function test_audit_log_lists_and_filters(): void
    {
        $this->actingAs($this->admin);
        $borrower = Borrower::create(['area_id' => $this->area->id, 'first_name' => 'Juan', 'last_name' => 'Cruz']);
        $borrower->update(['address' => 'Main St']);

        Livewire::test(ListActivities::class)
            ->assertOk()
            ->assertSee('Borrower: BRW-0001')
            ->filterTable('event', 'updated')
            ->assertCanSeeTableRecords(Activity::where('event', 'updated')->get())
            ->assertCanNotSeeTableRecords(Activity::where('event', 'created')->get())
            ->mountTableAction('view', Activity::where('event', 'updated')->first())
            ->assertHasNoErrors()
            ->assertOk();
    }
}
