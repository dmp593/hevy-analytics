@props(['url'])
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block; text-decoration: none;">
{{-- The mark, drawn with table cells and background colours rather than an
     image: no external request, so it survives "load images?" being answered
     no — which is the default in most desktop clients.

     Heights live on inner divs, not on the <td>: a height on an empty cell is
     advisory and Outlook collapses it. valign bottom lines the bars up on a
     baseline so they read as rising. --}}
<table cellpadding="0" cellspacing="0" role="presentation" style="display: inline-block; vertical-align: middle; border-collapse: collapse;">
<tr>
<td valign="bottom" style="padding: 0 2px;"><div style="width: 6px; height: 10px; background-color: #b3aef0; border-radius: 2px; font-size: 0; line-height: 0;">&nbsp;</div></td>
<td valign="bottom" style="padding: 0 2px;"><div style="width: 6px; height: 16px; background-color: #7c74e7; border-radius: 2px; font-size: 0; line-height: 0;">&nbsp;</div></td>
<td valign="bottom" style="padding: 0 2px;"><div style="width: 6px; height: 22px; background-color: #5147d9; border-radius: 2px; font-size: 0; line-height: 0;">&nbsp;</div></td>
</tr>
</table>
<span style="display: inline-block; vertical-align: middle; margin-left: 10px;">{!! $slot !!}</span>
</a>
</td>
</tr>
