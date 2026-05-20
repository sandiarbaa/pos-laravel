<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QR Sheet — Print Menu</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
        .qr-card { transition: box-shadow 0.2s; }
        .qr-card:hover { box-shadow: 0 4px 24px rgba(0,0,0,0.10); }
        .qr-card.hidden-by-search { display: none; }
        @media print {
            .no-print { display: none !important; }
            body { background: white !important; }
            .qr-card {
                break-inside: avoid;
                page-break-inside: avoid;
            }
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">

    {{-- Top Bar --}}
    <div class="no-print sticky top-0 z-10 bg-white border-b border-gray-200 px-6 py-4 shadow-sm">
        <div class="max-w-7xl mx-auto flex flex-col sm:flex-row sm:items-center gap-3 justify-between">
            <div>
                <h1 class="text-gray-900 font-bold text-lg">QR Sheet</h1>
                <p class="text-gray-500 text-sm">
                    <span id="product-count">{{ $products->count() }}</span> produk · Halo, <span class="text-gray-700 font-semibold">{{ $userName }}</span>
                </p>
            </div>

            {{-- Search --}}
            <div class="relative flex-1 max-w-xs">
                <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M17 11A6 6 0 1 1 5 11a6 6 0 0 1 12 0z"/>
                </svg>
                <input
                    id="search-input"
                    type="text"
                    placeholder="Cari produk..."
                    class="w-full pl-9 pr-4 py-2 border border-gray-200 rounded-xl text-sm bg-gray-50 focus:outline-none focus:border-gray-400 focus:bg-white transition-colors"
                >
            </div>

            <div class="flex items-center gap-2">
                <button
                    onclick="printAll()"
                    class="no-print flex items-center gap-2 bg-gray-900 text-white font-semibold px-4 py-2 rounded-xl text-sm hover:bg-gray-700 transition-colors"
                >
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                    </svg>
                    Print Semua
                </button>
                <button
                    onclick="downloadAllPDF()"
                    class="no-print flex items-center gap-2 bg-blue-600 text-white font-semibold px-4 py-2 rounded-xl text-sm hover:bg-blue-700 transition-colors"
                >
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                    </svg>
                    Download PDF
                </button>
                <a href="{{ route('qr.logout') }}" class="flex items-center gap-2 bg-red-500 hover:bg-red-600 text-white font-semibold px-4 py-2 rounded-xl text-sm transition-colors">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                    </svg>
                    Logout
                </a>
            </div>
        </div>
    </div>

    {{-- Loading overlay --}}
    <div id="loading-overlay" class="hidden fixed inset-0 bg-black/40 z-50 flex items-center justify-center">
        <div class="bg-white rounded-2xl px-8 py-6 text-center shadow-xl">
            <div class="w-10 h-10 border-4 border-blue-600 border-t-transparent rounded-full animate-spin mx-auto mb-3"></div>
            <p class="text-gray-700 font-semibold text-sm">Membuat PDF...</p>
        </div>
    </div>

    {{-- Content --}}
    <div class="max-w-7xl mx-auto px-6 py-8" id="qr-content">

        @if($products->isEmpty())
        <div class="text-center py-24">
            <p class="text-gray-400 text-lg">Belum ada produk aktif.</p>
        </div>
        @else

        @php
            $grouped = $products->groupBy(fn($p) => $p->category?->name ?? 'Tanpa Kategori');
        @endphp

        <div id="no-results" class="hidden text-center py-24">
            <p class="text-gray-400">Produk tidak ditemukan.</p>
        </div>

        @foreach($grouped as $categoryName => $items)
        <div class="category-section mb-10" data-category="{{ $categoryName }}">
            <div class="no-print flex items-center gap-3 mb-5">
                <div class="h-px flex-1 bg-gray-200"></div>
                <span class="text-gray-400 text-xs font-semibold uppercase tracking-widest">{{ $categoryName }}</span>
                <div class="h-px flex-1 bg-gray-200"></div>
            </div>

            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 gap-4">
                @foreach($items as $product)
                <div
                    class="qr-card bg-white border border-gray-200 rounded-2xl p-4 flex flex-col items-center gap-3"
                    data-name="{{ strtolower($product->name) }}"
                    id="card-{{ $product->id }}"
                >
                    {{-- QR --}}
                    <div class="bg-white rounded-xl w-full flex items-center justify-center" id="qr-{{ $product->id }}">
                        {!! \SimpleSoftwareIO\QrCode\Facades\QrCode::size(140)->errorCorrection('H')->generate((string) $product->id) !!}
                    </div>

                    {{-- Info --}}
                    <div class="text-center w-full">
                        <p class="text-gray-900 font-semibold text-sm leading-tight">{{ $product->name }}</p>
                        @if($product->sku)
                        <p class="text-gray-400 text-xs mt-0.5">SKU: {{ $product->sku }}</p>
                        @endif
                        <p class="text-gray-600 text-xs font-medium mt-1">Rp {{ number_format($product->price, 0, ',', '.') }}</p>
                    </div>

                    {{-- Download per produk --}}
                    <button
                        onclick="downloadSinglePDF({{ $product->id }}, '{{ addslashes($product->name) }}')"
                        class="no-print w-full flex items-center justify-center gap-1.5 border border-gray-200 text-gray-500 hover:text-gray-900 hover:border-gray-400 rounded-xl py-1.5 text-xs font-semibold transition-colors"
                    >
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                        </svg>
                        Download
                    </button>
                </div>
                @endforeach
            </div>
        </div>
        @endforeach

        @endif
    </div>

<script>
    // Search
    const searchInput = document.getElementById('search-input');
    const totalCount = {{ $products->count() }};

    searchInput.addEventListener('input', function () {
        const query = this.value.toLowerCase().trim();
        const cards = document.querySelectorAll('.qr-card');
        let visibleCount = 0;

        cards.forEach(card => {
            const name = card.dataset.name || '';
            const match = name.includes(query);
            card.classList.toggle('hidden-by-search', !match);
            if (match) visibleCount++;
        });

        // Hide empty category sections
        document.querySelectorAll('.category-section').forEach(section => {
            const visibleCards = section.querySelectorAll('.qr-card:not(.hidden-by-search)');
            section.style.display = visibleCards.length === 0 ? 'none' : '';
        });

        document.getElementById('product-count').textContent = visibleCount;
        document.getElementById('no-results').classList.toggle('hidden', visibleCount > 0);
    });

    // Print semua
    function printAll() {
        window.print();
    }

    // Download PDF semua produk
    async function downloadAllPDF() {
        const { jsPDF } = window.jspdf;
        const overlay = document.getElementById('loading-overlay');
        overlay.classList.remove('hidden');

        const pdf = new jsPDF('p', 'mm', 'a4');
        const cards = document.querySelectorAll('.qr-card:not(.hidden-by-search)');
        const pageWidth = 210;
        const margin = 10;
        const cols = 4;
        const cellW = (pageWidth - margin * 2) / cols;
        const cellH = 55;
        let col = 0, row = 0;

        for (const card of cards) {
            // Sembunyikan tombol download saat capture
            const btn = card.querySelector('button');
            if (btn) btn.style.display = 'none';

            const canvas = await html2canvas(card, { scale: 2, backgroundColor: '#ffffff' });
            const imgData = canvas.toDataURL('image/png');

            if (btn) btn.style.display = '';

            const x = margin + col * cellW;
            const y = margin + row * cellH;

            if (col === 0 && row === 0 && cards[0] !== card) {
                // bukan halaman pertama — sudah di-handle di bawah
            }

            pdf.addImage(imgData, 'PNG', x, y, cellW - 2, cellH - 2);
            col++;
            if (col >= cols) {
                col = 0;
                row++;
                if (y + cellH * 2 > 287 && card !== cards[cards.length - 1]) {
                    pdf.addPage();
                    row = 0;
                }
            }
        }

        overlay.classList.add('hidden');
        pdf.save('qr-sheet-semua-produk.pdf');
    }

    // Download PDF satu produk
    async function downloadSinglePDF(productId, productName) {
        const { jsPDF } = window.jspdf;
        const card = document.getElementById('card-' + productId);
        const btn = card.querySelector('button');
        if (btn) btn.style.display = 'none';

        const canvas = await html2canvas(card, { scale: 3, backgroundColor: '#ffffff' });
        const imgData = canvas.toDataURL('image/png');
        if (btn) btn.style.display = '';

        const pdf = new jsPDF('p', 'mm', [70, 80]);
        pdf.addImage(imgData, 'PNG', 5, 5, 60, 70);
        pdf.save('qr-' + productName.replace(/\s+/g, '-').toLowerCase() + '.pdf');
    }
</script>

</body>
</html>
