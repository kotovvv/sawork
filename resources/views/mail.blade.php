<html>
<head>
<meta http-equiv="content-type" content="text/html; charset=UTF-8"/>
<title>{{ 'Zwrot od odbiorcy nr ' . $docWZk->NrDokumentu }}</title>
<style type="text/css">
p{ padding:0; margin: 0; }
body { font-family: 'DejaVu Sans', sans-serif; }
.font0 { font:10pt 'DejaVu Sans',Arial, sans-serif; }
.font1 { font:12pt 'DejaVu Sans',Arial, sans-serif; }
.font2 { font:15pt 'DejaVu Sans',Arial, sans-serif; }
</style>
</head>
<body>

<p><span class="font2" style="font-weight:bold;">Zwrot od odbiorcy nr {{ $docWZk->NrDokumentu}}</span></p>
<p><span class="font1" style="font-style:italic;">Magazyn:</span><span class="font1"> {{ $Magazyn->Nazwa }}</span>
</p>
<p><span class="font1" style="font-style:italic;">Data operacji:</span><span class="font1"> {{ $docWZk->Data }}</span></p>
<table border="0" style="margin-top: 30px;" width="100%">
<tbody>
<tr>
<td width="50%" style="vertical-align: top;">
<p><span class="font0" style="font-style: italic;">.</span></p>
<p><span class="font1" style="font-weight: bold;">{{ $my->Nazwa }}</span></p>
<p><span class="font1">{{ $my->UlicaLokal }}</span></p>
<p><span class="font1">{{$my->KodPocztowy}} {{$my->Miejscowosc}}</span></p>
<p><span class="font1">{{$my->Telefon}}</span></p>
<p><span class="font0">{{ $docWZk->Uwagi}}</span></p>
</td>
<td style="vertical-align: top;">
<p><span class="font0" style="font-style: italic;">Kontrahent:</span></p>
<p><span class="font1" style="font-weight: bold;">{{$docWZk->Nazwa}}</span></p>
<p><span class="font1">{{$docWZk->UlicaLokal}}</span></p>
<p><span class="font1">{{$docWZk->KodPocztowy}} {{$docWZk->Miejscowosc}}</span></p>
<p><span class="font1">{{$docWZk->Telefon}}</span></p>
</td>
</tr>
</tbody>
</table>
<table border="1" cellspacing="0"  cellpadding="2" width="100%" style="margin-top: 20px; width: 100%;">
<tbody>
<tr style="background-color: #dddddd;">
<th style="vertical-align: bottom;">
<p style="text-align: center;"><span class="font0">Nazwa towaru</span></p>
</th>
<th style="vertical-align: bottom;">
<p style="text-align: center;"><span class="font0">Kod kreskowy</span></p>
</th>
<th style="vertical-align: bottom;">
<p style="text-align: center;"><span class="font0">Cena</span></p>
</th>
<th style="vertical-align: bottom;">
<p style="text-align: center;"><span class="font0">Ilość</span></p>
</th>
<th style="vertical-align: bottom;">
<p style="text-align: center;"><span class="font0">Jedn.</span></p>
</th>
<th style="vertical-align: bottom;">
<p style="text-align: center;"><span class="font0">Wartość</span></p>
</th>
<th style="vertical-align: bottom;">
<p style="text-align: center;"><span class="font0">Uwagi</span></p>
</th>
</tr>
@if (count($products) )
@php
    $counter = 0;
@endphp
@foreach ($products as $product)
@php
    $counter += $product->Ilosc;
@endphp
<tr>
<td style="vertical-align: middle;"> <p><span class="font0">{{ $product->Nazwa}}}</span></p> </td>
<td style="vertical-align: middle;"> <p><span class="font0">{{ $product->KodKreskowy}}</span></p> </td>
<td style="vertical-align: middle;"> <p style="text-align: right;"><span class="font0">{{ round($product->CenaJednostkowa,2)}}</span></p> </td>
<td style="vertical-align: middle;"> <p style="text-align: right;"><span class="font0">{{ round($product->Ilosc,0)}}</span></p> </td>
<td style="vertical-align: middle;"> <p style="text-align: center;"><span class="font0">{{ $product->ed}}</span></p> </td>
<td style="vertical-align: middle;"> <p style="text-align: right;"><span class="font0">{{ round($product->Ilosc * $product->CenaJednostkowa,2) }}</span></p> </td>
<td style="vertical-align: middle;"> <p><span class="font0">{{ $product->Uwagi}}</span></p> </td>
</tr>
@endforeach
@endif

<tr>
<td style="border-style: none;">
<p><span class="font0" style="font-weight: bold;">Razem</span></p>
</td>
<td style="border-style: none;"></td>
<td style="border-style: none;"></td>
<td style="border-style: none;">
<p style="text-align: right;"><span class="font0" style="font-weight: bold;">{{$counter}}</span></p>
</td>
<td style="border-style: none;"></td>
<td style="border-style: none;">
<p style="text-align: right;"><span class="font0" style="font-weight: bold;">{{round($docWZk->WartoscDokumentu,2)}}</span></p>
</td>
<td style="border-style: none;"></td>
</tr>
</tbody>
</table>
<table border="0" style="margin-top: 60px;" width="100%">
<tbody>
<tr>
<td style="border-top: 2px dotted;">
<p style="text-align: center;"><span class="font0" style="font-style: italic;">Wystawił</span></p>
</td>
<td></td>
<td style="border-top: 2px dotted;">
<p style="text-align: center;"><span class="font0" style="font-style: italic;">Wydał</span></p>
</td>
<td></td>
<td style="border-top: 2px dotted;">
<p style="text-align: center;"><span class="font0" style="font-style: italic;">Odebrał</span></p>
</td>
</tr>
</tbody>
</table>
</body>
</html>
