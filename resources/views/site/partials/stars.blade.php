@php $r = (int) round($rating ?? 0); @endphp
<span class="inline-flex text-honey" aria-label="{{ $r }}/5">@for ($i = 1; $i <= 5; $i++)<span class="{{ $i <= $r ? '' : 'text-line' }}">★</span>@endfor</span>
