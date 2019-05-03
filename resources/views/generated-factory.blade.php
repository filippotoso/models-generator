
use Faker\Generator as Faker;
use Illuminate\Support\Str;
use App\{!! $model !!};

$factory->define({!! $model !!}::class, function (Faker $faker) {
    return [
@foreach($columns as $columnName => $columnValue)
@if (class_exists($columnValue))
        '{!! $columnName !!}' => function () {
            return factory({!! $columnValue !!}::class)->create()->id;
        },
@else
        '{!! $columnName !!}' => {!! $columnValue !!},
@endif
@endforeach
    ];
});
