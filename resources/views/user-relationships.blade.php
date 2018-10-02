
namespace App\Models\Traits;

@foreach ($relationships as $relationship)
use App\{{ $relationship['class'] }};
@endforeach

trait UserRelationships  {

@foreach ($relationships as $relationship)
    @include('models-generator::relationship', ['relationship' => $relationship])
@endforeach
}
