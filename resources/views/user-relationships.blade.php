
namespace {!! $namespace !!}\Traits;

trait UserRelationships  {

@foreach ($relationships as $relationship)
    @include('models-generator::relationship', ['relationship' => $relationship])
@endforeach
}
