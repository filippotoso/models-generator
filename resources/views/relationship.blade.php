@if ($relationship['type'] == 'hasOne')
    /**
    * Get the single record associated with this model.
    */
    public function {{ $relationship['name'] }}()
    {
        return $this->hasOne({{ $relationship['class'] }}::class, '{{ $relationship['foreign_key'] }}', '{{ $relationship['local_key'] }}');
    }

@endif
@if ($relationship['type'] == 'hasMany')
    /**
    * Get the multiple records associated with this model.
    */
    public function {{ $relationship['name'] }}()
    {
        return $this->hasMany({{ $relationship['class'] }}::class, '{{ $relationship['foreign_key'] }}', '{{ $relationship['local_key'] }}');
    }

@endif
@if ($relationship['type'] == 'belongsTo')
    /**
    * Get the single record associated with this model.
    */
    public function {{ $relationship['name'] }}()
    {
        return $this->belongsTo({{ $relationship['class'] }}::class, '{{ $relationship['foreign_key'] }}', '{{ $relationship['local_key'] }}');
    }

@endif
@if ($relationship['type'] == 'belongsToMany')
    /**
    * The records that belong to this model.
    */
    public function {{ $relationship['name'] }}()
    {
        return $this->belongsToMany({{ $relationship['class'] }}::class, '{{ $relationship['table'] }}', '{{ $relationship['foreign_key'] }}', '{{ $relationship['local_key'] }}');
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
        return $this->morphMany({{ $relationship['class'] }}::class, '{{ $relationship['relationship'] }}');
    }

@endif
