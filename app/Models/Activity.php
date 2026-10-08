<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;
use Spatie\Activitylog\Models\Activity as BaseActivity;

/**
 * Audit log entry. Entries are append-only: they cannot be edited or deleted
 * from the application (Agreement §2.3).
 */
class Activity extends BaseActivity
{
    public const SUBJECT_LABELS = [
        Area::class => 'Area',
        Borrower::class => 'Borrower',
        Loan::class => 'Loan',
        Payment::class => 'Payment',
        SavingsAccount::class => 'Savings account',
        SavingsTransaction::class => 'Savings transaction',
        User::class => 'User',
    ];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Audit log entries cannot be edited.'));
        static::deleting(fn () => throw new LogicException('Audit log entries cannot be deleted.'));
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    public function subjectLabel(): string
    {
        if (! $this->subject_type) {
            return '—';
        }

        $type = static::SUBJECT_LABELS[$this->subject_type] ?? class_basename($this->subject_type);
        $subject = $this->subject;

        $name = match (true) {
            $subject instanceof Borrower => $subject->reference_no.' · '.$subject->full_name,
            $subject instanceof Loan => $subject->loan_no,
            $subject instanceof Payment => '₱'.number_format((float) $subject->amount, 2).' on '.($subject->loan?->loan_no ?? 'loan #'.$subject->loan_id),
            $subject instanceof SavingsAccount => $subject->account_no,
            $subject instanceof SavingsTransaction => $subject->type->getLabel().' ₱'.number_format((float) $subject->amount, 2),
            $subject instanceof User => $subject->name,
            $subject instanceof Area => $subject->name,
            default => '#'.$this->subject_id,
        };

        return $type.': '.$name;
    }
}
