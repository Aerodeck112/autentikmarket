@extends('errors.layout')
@section('code')
4<span>2</span>9
@endsection
@section('title', app()->getLocale() === 'hu' ? 'Túl sok kérés' : 'Prea multe cereri')
@section('message', app()->getLocale() === 'hu' ? 'Kérjük, várj egy kicsit, mielőtt újra próbálkozol.' : 'Te rugăm să aștepți puțin înainte de a încerca din nou.')
