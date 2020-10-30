
namespace App\Models\Support;

@if (isset($softDeletes) && $softDeletes)
use Illuminate\Database\Eloquent\SoftDeletes;
@endif
@if (empty($uses))

@else
@foreach ($uses as $use)
use App\Models\{{ $use }};
@endforeach

@endif
class {{ $class }} extends BaseModel {

@if (isset($softDeletes) && $softDeletes)
    use SoftDeletes;

@endif
    /**
    * The table associated with the model.
    *
    * @var string
    */
    protected $table = '{{ $table }}';

@if (isset($primaryKey) && !is_null($primaryKey) && ($primaryKey != 'id'))
    /**
    * The primary key for the model.
    *
    * @var string
    */
    protected $primaryKey = '{{ $primaryKey }}';

@endif
@if (isset($incrementing) && !$incrementing)
    /**
    * Indicates if the IDs are auto-incrementing.
    *
    * @var bool
    */
    public $incrementing = false;

@endif
@if (isset($fillable) && is_array($fillable) && !empty($fillable))
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        '{!! implode("', '", $fillable) !!}',
    ];

@endif
@if (isset($attributes) && is_array($attributes) && !empty($attributes))
    /**
    * The model's attributes.
    *
    * @var array
    */
    protected $attributes = @exportModelProperty($attributes)

@endif
@if (isset($casts) && is_array($casts) && !empty($casts))
    /**
    * The attributes that should be cast to native types.
    *
    * @var array
    */
    protected $casts = @exportModelProperty($casts)

@endif
@if (isset($dates) && is_array($dates) && !empty($dates))
    /**
    * The attributes that should be mutated to dates.
    *
    * @var array
    */
    protected $dates = [
        '{!! implode("', '", $dates) !!}',
    ];

@endif
@if (isset($timestamps) && !$timestamps)
    /**
    * Indicates if the model should be timestamped.
    *
    * @var bool
    */
    public $timestamps = false;

@endif
@foreach ($relationships as $relationship)
    @include('models-generator::relationship', ['relationship' => $relationship])
@endforeach
}
