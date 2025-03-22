<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Contact</title>
</head>
<body>
<nav>
    <a href="/"> Home</a>
    <a href="/about"> About</a>
    <a href="/contact"> Contact</a>
</nav>


{{--Short Form--}}
{{ $slot }}

{{--Long form--}}
{{--<?php echo $slot ?>--}}

</body>
</html>
