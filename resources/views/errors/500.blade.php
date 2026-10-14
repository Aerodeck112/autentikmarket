@extends('errors.layout')
@section('code')
5<span>0</span>0
@endsection
@section('title', app()->getLocale() === 'hu' ? 'Hiba történt' : 'A apărut o eroare')
@section('message', app()->getLocale() === 'hu' ? 'Sajnáljuk — a csapat értesítést kapott. Kérjük, próbáld újra néhány perc múlva.' : 'Ne pare rău — echipa a fost notificată. Te rugăm să încerci din nou în câteva minute.')
