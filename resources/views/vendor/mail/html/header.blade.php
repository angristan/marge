@props(['url'])
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block;">
<img src="{{ rtrim(config('app.url'), '/') }}/bulla.png" class="logo" alt="{{ config('app.name') }}" style="max-width: 150px; height: auto;">
</a>
</td>
</tr>
