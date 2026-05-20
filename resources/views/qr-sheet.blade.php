<!DOCTYPE html>
<html>
<head>
<style>
  body { font-family: Arial; }
  .grid { display: flex; flex-wrap: wrap; gap: 16px; padding: 16px; }
  .card {
    border: 1px solid #ccc;
    padding: 12px;
    text-align: center;
    width: 160px;
    border-radius: 8px;
  }
  .name { font-weight: bold; margin-top: 8px; font-size: 13px; }
  .price { color: #666; font-size: 12px; }
  @media print { .no-print { display: none; } }
</style>
</head>
<body>
  <button class="no-print" onclick="window.print()">🖨️ Print</button>
  <div class="grid">
    @foreach($products as $product)
    <div class="card">
      {!! QrCode::size(120)->generate((string) $product->id) !!}
      <div class="name">{{ $product->name }}</div>
      <div class="price">Rp {{ number_format($product->price) }}</div>
    </div>
    @endforeach
  </div>
</body>
</html>
