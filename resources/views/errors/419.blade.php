@extends('errors.layout')
@section('code')
4<span>1</span>9
@endsection
@section('title', app()->getLocale() === 'hu' ? 'A munkamenet lejárt' : 'Sesiunea a expirat')
@section('message', app()->getLocale() === 'hu' ? 'Biztonsági okokból az oldal lejárt. Töltsd újra és próbáld meg ismét.' : 'Din motive de securitate, pagina a expirat. Reîncarcă și încearcă din nou.')
