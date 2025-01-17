<html>
<head>
<meta http-equiv="content-type" content="text/html; charset=UTF-8"/>
<title>Informacja dotycząca zwrotu zamówienia nr {{$WZ->NrDokumentu}}  {{$WZ->Data}}</title>
<style type="text/css">
p{ padding:0; margin: 0; }
body { font-family: 'DejaVu Sans', sans-serif; }
.font0 { font:10pt 'DejaVu Sans',Arial, sans-serif; }
.font1 { font:12pt 'DejaVu Sans',Arial, sans-serif; }
.font2 { font:15pt 'DejaVu Sans',Arial, sans-serif; }
</style>
</head>
<body>
<p><span class="font2" style="font-weight:bold;"> Dzień dobry,</span></p>
<p><span class="font1">W załączniku przesyłamy informacje dotyczące zwrotu zamówienia {{$WZ->NrDokumentu}} z dnia {{$WZ->Data}} </span></p>

<p style="margin:30px 0"><span class="font1">Zwrot od odbiorcy nr {{$WZk->NrDokumentu}} z dnia {{$WZk->Data}} </span></p>

<table border="1" cellspacing="0"  cellpadding="2" width="100%" style="margin-top: 20px; width: 100%;">
<tbody>
<tr style="background-color: #dddddd;">
<th style="vertical-align: bottom;">
<p style="text-align: center;"><span class="font0">SKU</span></p>
</th>
<th style="vertical-align: bottom;">
<p style="text-align: center;"><span class="font0">Kod kreskowy</span></p>
</th>
<th style="vertical-align: bottom;">
<p style="text-align: center;"><span class="font0">Nazwa</span></p>
</th>
<th style="vertical-align: bottom;">
<p style="text-align: center;"><span class="font0">Ilość</span></p>
</th>
<th style="vertical-align: bottom;">
<p style="text-align: center;"><span class="font0">Status</span></p>
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
<td style="vertical-align: middle;"> <p><span class="font0">{{ $product->SKU}}</span></p> </td>
<td style="vertical-align: middle;"> <p><span class="font0">{{ $product->KodKreskowy}}</span></p> </td>
<td style="vertical-align: middle;"> <p><span class="font0">{{ $product->Nazwa}}</span></p> </td>
<td style="vertical-align: middle;"> <p style="text-align: right;"><span class="font0">{{ round($product->Ilosc,0)}}</span></p> </td>
<td style="vertical-align: middle;"> <p style="text-align: center;"><span class="font0">{{ $product->status}}</span></p> </td>
<td style="vertical-align: middle;"> <p><span class="font0">{{ $product->Uwagi}}</span></p> </td>
</tr>
@endforeach
@endif
</tbody>
</table>

<p style="margin: 30px 0"><span class="font1" style="font-style:italic;"> Dołączamy do wiadomości zdjęcia otrzymanego towaru z udokumentowanymi uszkodzeniami. </span></p>
<p style="margin-top: 50px"><span class="font1" style="font-style:italic;"> Pozdrawiamy, <br> Zespół Fulstor </span></p>
</body>
</html>
