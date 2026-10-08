@if ($areas->isNotEmpty())
    <label>Area
        <select name="area">
            <option value="">All areas</option>
            @foreach ($areas as $id => $name)
                <option value="{{ $id }}" @selected($areaId === $id)>{{ $name }}</option>
            @endforeach
        </select>
    </label>
@endif
