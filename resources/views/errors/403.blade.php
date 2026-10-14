@extends('errors.layout')
@section('code')
4<span>0</span>3
@endsection
@section('title', app()->getLocale() === 'hu' ? 'Hozzáférés megtagadva' : 'Acces interzis')
@section('message', app()->getLocale() === 'hu' ? 'Nincs jogosultságod az oldal megtekintéséhez.' : 'Nu ai permisiunea de a accesa această pagină.')
