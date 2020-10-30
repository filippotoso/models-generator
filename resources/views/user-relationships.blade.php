
namespace App\Models\Traits;

@foreach ($uses as $use)
use App\Models\{{ $use }};
@endforeach

trait UserRelationships  {

@foreach ($relationships as $relationship)
    @include('models-generator::relationship', ['relationship' => $relationship])
@endforeach
}
