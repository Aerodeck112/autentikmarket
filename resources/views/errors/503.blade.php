@extends('errors.layout')
{{-- Pagina de maintenance este pre-randată la `artisan down`, deci afișăm ambele limbi. --}}
@section('code')
5<span>0</span>3
@endsection
@section('title', 'Revenim imediat · Hamarosan visszatérünk')
@section('message')
Platforma este în curs de actualizare. Revino în câteva minute.<br><br>A platform frissítés alatt áll. Nézz vissza néhány perc múlva.
@endsection
@section('nolink', true)
