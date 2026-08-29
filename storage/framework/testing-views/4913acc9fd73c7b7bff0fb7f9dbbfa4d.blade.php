<?php extract((new \Illuminate\Support\Collection($attributes->getAttributes()))->mapWithKeys(function ($value, $key) { return [Illuminate\Support\Str::camel(str_replace([':', '.'], ' ', $key)) => $value]; })->all(), EXTR_SKIP); ?>

<x-heroicon-o-arrow-right-end-on-rectangle  {{ $attributes }}>

{{ $slot ?? "" }}
</x-heroicon-o-arrow-right-end-on-rectangle>