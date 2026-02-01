@foreach($photos as $photo)
    @include('photos.partials.photo-card', ['photo' => $photo])
@endforeach
