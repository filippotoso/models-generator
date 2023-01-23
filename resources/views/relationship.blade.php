@if ($relationship['type'] == 'hasOne')
    /**
    * Get the single record associated with this model.
    */
    public function {{ $relationship['name'] }}()
    {
        return $this->hasOne(\{!! $modelsNamespace !!}\{{ $relationship['class'] }}::class, '{{ $relationship['foreign_key'] }}', '{{ $relationship['local_key'] }}');
    }

@endif
@if ($relationship['type'] == 'hasMany')
    /**
    * Get the multiple records associated with this model.
    */
    public function {{ $relationship['name'] }}()
    {
        return $this->hasMany(\{!! $modelsNamespace !!}\{{ $relationship['class'] }}::class, '{{ $relationship['foreign_key'] }}', '{{ $relationship['local_key'] }}');
    }

@endif
@if ($relationship['type'] == 'belongsTo')
    /**
    * Get the single record associated with this model.
    */
    public function {{ $relationship['name'] }}()
    {
        return $this->belongsTo(\{!! $modelsNamespace !!}\{{ $relationship['class'] }}::class, '{{ $relationship['foreign_key'] }}', '{{ $relationship['local_key'] }}');
    }

@endif
@if ($relationship['type'] == 'belongsToMany')
    /**
    * The records that belong to this model.
    */
    public function {{ $relationship['name'] }}()
    {
@if (($relationship['timestamps'] == false) && (count($relationship['columns']) == 0))
        return $this->belongsToMany(\{!! $modelsNamespace !!}\{{ $relationship['class'] }}::class, '{{ $relationship['table'] }}', '{{ $relationship['foreign_key'] }}', '{{ $relationship['local_key'] }}');
@elseif ($relationship['timestamps'] && (count($relationship['columns']) > 0))
        return $this->belongsToMany(\{!! $modelsNamespace !!}\{{ $relationship['class'] }}::class, '{{ $relationship['table'] }}', '{{ $relationship['foreign_key'] }}', '{{ $relationship['local_key'] }}')
            ->withTimestamps()
            ->withPivot('{!! implode("', '", $relationship['columns']) !!}');
@elseif ($relationship['timestamps'])
        return $this->belongsToMany(\{!! $modelsNamespace !!}\{{ $relationship['class'] }}::class, '{{ $relationship['table'] }}', '{{ $relationship['foreign_key'] }}', '{{ $relationship['local_key'] }}')
            ->withTimestamps();
@elseif (count($relationship['columns']) > 0)
        return $this->belongsToMany(\{!! $modelsNamespace !!}\{{ $relationship['class'] }}::class, '{{ $relationship['table'] }}', '{{ $relationship['foreign_key'] }}', '{{ $relationship['local_key'] }}')
            ->withPivot('{!! implode("', '", $relationship['columns']) !!}');
@endif
    }
@endif
@if ($relationship['type'] == 'morphTo')
    /**
    * Get all of the owning polymorphic models.
    */
    public function {{ $relationship['name'] }}()
    {
        return $this->morphTo();
    }

@endif
@if ($relationship['type'] == 'morphMany')
    /**
    * Get all of the polymorphic models.
    */
    public function {{ $relationship['name'] }}()
    {
        return $this->morphMany(\{!! $modelsNamespace !!}\{{ $relationship['class'] }}::class, '{{ $relationship['relationship'] }}');
    }

@endif
