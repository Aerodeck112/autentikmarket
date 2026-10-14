@extends('errors.layout')
@section('code')
4<span>0</span>4
@endsection
@section('title', app()->getLocale() === 'hu' ? 'Az oldal nem található' : 'Pagina nu a fost găsită')
@section('message', app()->getLocale() === 'hu' ? 'Lehet, hogy áthelyezték vagy már nem létezik. Gyere vissza a vásárba.' : 'Poate a fost mutată sau nu mai există. Hai înapoi la târg.')
