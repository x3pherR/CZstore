<?php
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

$dataFile = __DIR__ . '/catalog.json';
$whatsappFile = __DIR__ . '/whatsapp.txt';
$textsFile = __DIR__ . '/site_texts.json';

$catalog = file_exists($dataFile) ? json_decode(file_get_contents($dataFile), true) : [];
$siteTexts = file_exists($textsFile) ? json_decode(file_get_contents($textsFile), true) : [];
$serverWhatsapp = file_exists($whatsappFile) ? trim(file_get_contents($whatsappFile)) : '5491100000000';

$productId = $_GET['id'] ?? '';
$product = null;

foreach ($catalog as $item) {
    if ($item['id'] === $productId) {
        $product = $item;
        break;
    }
}

if (!$product) {
    header('Location: index.php');
    exit;
}

// Consolidar imágenes y videos para la galería
$mediaItems = [];
if (!empty($product['image'])) {
    $mediaItems[] = $product['image'];
}
if (!empty($product['gallery']) && is_array($product['gallery'])) {
    foreach ($product['gallery'] as $g) {
        if (!in_array($g, $mediaItems)) $mediaItems[] = $g;
    }
}
?>
<!DOCTYPE html>
<html lang="es" class="dark scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?php echo htmlspecialchars($product['brand'] . ' - ' . $product['title']); ?> | CZSTORE</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@600;800;900&family=Montserrat:wght@300;400;500;600;700;800&family=Cinzel:wght@600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        obsidian: '#07080a',
                        charcoal: '#111319',
                        gold: { 100: '#FAF3DC', 300: '#E5C875', 500: '#D4AF37', 600: '#C5A059' }
                    },
                    fontFamily: {
                        brand: ['Orbitron', 'sans-serif'],
                        serif: ['Cinzel', 'serif'],
                        sans: ['Montserrat', 'sans-serif']
                    }
                }
            }
        }
    </script>
    <style>
        ::-webkit-scrollbar { width: 4px; height: 4px; }
        ::-webkit-scrollbar-track { background: #07080a; }
        ::-webkit-scrollbar-thumb { background: #232734; border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: #D4AF37; }

        .gold-gradient-bg { background: linear-gradient(135deg, #FAF3DC 0%, #D4AF37 50%, #9A7B31 100%); }
        .glass-header { background: rgba(7, 8, 10, 0.92); backdrop-filter: blur(16px); }
        .bg-pattern { background-color: #07080a; background-image: radial-gradient(rgba(212, 175, 55, 0.06) 1px, transparent 0); background-size: 28px 28px; }
        .drawer-transition { transition: transform 0.35s cubic-bezier(0.16, 1, 0.3, 1); }
        .scrollbar-none::-webkit-scrollbar { display: none; }
        .scrollbar-none { -ms-overflow-style: none; scrollbar-width: none; }
        
        button, a, input, select { touch-action: manipulation; }
    </style>
</head>
<body class="bg-obsidian text-gray-200 font-sans antialiased bg-pattern min-h-screen flex flex-col justify-between selection:bg-gold-500 selection:text-black">

    <!-- HEADER -->
    <header class="sticky top-0 z-40 glass-header border-b border-gold-500/20">
        <div class="max-w-7xl mx-auto px-3 sm:px-6 lg:px-8 h-14 sm:h-16 flex items-center justify-between">
            <a href="index.php" class="flex items-center gap-2">
                <div class="w-7 h-7 sm:w-8 sm:h-8 rounded-lg border border-gold-500/60 flex items-center justify-center bg-charcoal">
                    <i class="fa-solid fa-clock text-gold-500 text-xs sm:text-sm"></i>
                </div>
                <div>
                    <span class="font-brand font-black text-sm sm:text-lg tracking-[0.15em] text-white block leading-none">CZ<span class="text-gold-500">STORE</span></span>
                    <span class="text-[7px] sm:text-[8px] font-semibold tracking-[0.15em] text-gray-400 uppercase block mt-0.5">Exclusivos &amp; Réplicas</span>
                </div>
            </a>

            <div class="flex items-center gap-2 sm:gap-3">
                <a href="index.php" class="text-[10px] sm:text-xs text-gray-400 hover:text-gold-500 font-bold uppercase tracking-wider flex items-center gap-1">
                    <i class="fa-solid fa-arrow-left"></i> Volver
                </a>
                <button onclick="toggleCartDrawer(true)" class="relative flex items-center gap-1.5 px-2.5 py-1.5 sm:px-3 sm:py-2 rounded-full border border-gold-500/50 bg-gold-500/10 hover:bg-gold-500/20 text-gold-300 transition-all active:scale-95">
                    <i class="fa-solid fa-bag-shopping text-gold-500 text-xs"></i>
                    <span class="text-[10px] font-bold tracking-wider hidden sm:inline">CARRITO</span>
                    <span id="cart-badge-count" class="bg-gold-500 text-obsidian text-[9px] font-black px-1.5 py-0.2 rounded-full min-w-[16px] text-center">0</span>
                </button>
            </div>
        </div>
    </header>

    <!-- DETALLE DE PRODUCTO COMPACTO -->
    <main class="max-w-6xl mx-auto px-3 sm:px-6 lg:px-8 py-4 sm:py-8 flex-1 w-full">
        <div class="grid grid-cols-1 md:grid-cols-12 gap-4 sm:gap-8 items-start">
            
            <!-- GALERÍA SLIDER DE DESLIZAMIENTO COMPACTA -->
            <div class="md:col-span-6 space-y-2">
                <div id="media-slider" class="flex gap-2 overflow-x-auto snap-x snap-mandatory scrollbar-none rounded-2xl bg-charcoal border border-white/10 aspect-square w-full">
                    <?php foreach ($mediaItems as $idx => $url): 
                        $isVideo = preg_match('/\.(mp4|webm|mov|m4v)$/i', $url);
                    ?>
                        <div class="w-full h-full shrink-0 snap-start flex items-center justify-center relative bg-obsidian overflow-hidden group cursor-pointer" id="slide-<?php echo $idx; ?>" onclick="openZoomModal(<?php echo $idx; ?>)">
                            <?php if ($isVideo): ?>
                                <video src="<?php echo htmlspecialchars($url); ?>" controls class="w-full h-full object-contain" onclick="event.stopPropagation()"></video>
                            <?php else: ?>
                                <img src="<?php echo htmlspecialchars($url); ?>" class="w-full h-full object-contain">
                                <div class="absolute bottom-2 right-2 bg-obsidian/80 border border-gold-500/40 text-gold-300 p-2 rounded-full opacity-0 group-hover:opacity-100 transition-opacity">
                                    <i class="fa-solid fa-magnifying-glass-plus text-xs"></i>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- MINIATURAS NAVEGABLES -->
                <div class="flex items-center gap-1.5 overflow-x-auto pb-1 scrollbar-none justify-center" id="thumbnails-container">
                    <?php foreach ($mediaItems as $idx => $url): 
                        $isVideo = preg_match('/\.(mp4|webm|mov|m4v)$/i', $url);
                    ?>
                        <button onclick="scrollToSlide(<?php echo $idx; ?>)" class="thumb-btn relative w-12 h-12 sm:w-14 sm:h-14 rounded-lg bg-charcoal border border-white/10 overflow-hidden shrink-0 transition-all hover:border-gold-500/50" data-index="<?php echo $idx; ?>">
                            <?php if ($isVideo): ?>
                                <video src="<?php echo htmlspecialchars($url); ?>" class="w-full h-full object-cover pointer-events-none"></video>
                                <i class="fa-solid fa-play absolute inset-0 m-auto text-gold-500 text-[10px] w-3 h-3 flex items-center justify-center"></i>
                            <?php else: ?>
                                <img src="<?php echo htmlspecialchars($url); ?>" class="w-full h-full object-cover">
                            <?php endif; ?>
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- INFORMACIÓN Y COMPRA -->
            <div class="md:col-span-6 space-y-4 bg-charcoal/40 p-4 sm:p-6 rounded-2xl border border-white/10">
                <div>
                    <span class="text-[10px] font-bold text-gold-500 tracking-widest uppercase block"><?php echo htmlspecialchars($product['brand']); ?></span>
                    <h1 class="font-serif text-lg sm:text-2xl font-bold text-white leading-tight mt-0.5"><?php echo htmlspecialchars($product['title']); ?></h1>
                    <span class="inline-block mt-1.5 px-2.5 py-0.5 rounded-full bg-gold-500/10 border border-gold-500/30 text-gold-300 text-[9px] font-bold uppercase tracking-wider">
                        <?php echo htmlspecialchars($product['category']); ?>
                    </span>
                </div>

                <!-- PRECIOS -->
                <div class="p-3 rounded-xl bg-obsidian border border-white/10 space-y-1">
                    <?php 
                        $basePrice = $product['price'];
                        if (!empty($product['discountPercent']) && $product['discountPercent'] > 0) {
                            $basePrice = $basePrice * (1 - $product['discountPercent'] / 100);
                        }
                        $transferDiscount = $product['transferDiscountPercent'] ?? 0;
                        $transferPrice = $transferDiscount > 0 ? $basePrice * (1 - $transferDiscount / 100) : $basePrice;
                    ?>
                    
                    <div class="flex items-baseline justify-between">
                        <span class="text-[10px] text-gray-400 font-bold uppercase">Precio Lista:</span>
                        <span class="text-sm font-serif font-bold text-white">$ <?php echo number_format($basePrice, 0, ',', '.'); ?> ARS</span>
                    </div>

                    <?php if ($transferDiscount > 0): ?>
                        <div class="flex items-baseline justify-between border-t border-white/10 pt-1 text-emerald-400">
                            <span class="text-[10px] sm:text-[11px] font-bold uppercase flex items-center gap-1 drop-shadow-[0_0_8px_rgba(52,211,153,0.6)]">
                                <i class="fa-solid fa-bolt text-[9px]"></i> Transferencia (<?php echo $transferDiscount; ?>% OFF):
                            </span>
                            <span class="text-base font-serif font-bold text-emerald-400 drop-shadow-[0_0_8px_rgba(52,211,153,0.6)]">$ <?php echo number_format($transferPrice, 0, ',', '.'); ?> ARS</span>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- SELECCIÓN DE COLOR / VARIACIÓN -->
                <?php if (!empty($product['colors']) && is_array($product['colors'])): ?>
                    <div class="space-y-1.5">
                        <label class="block text-[10px] font-bold text-gold-500 uppercase">Variante / Color:</label>
                        <div class="grid grid-cols-2 gap-1.5" id="color-options">
                            <?php foreach ($product['colors'] as $cIdx => $c): ?>
                                <button onclick="selectColor('<?php echo htmlspecialchars($c['name']); ?>', this)" 
                                    class="color-btn p-2 rounded-lg border text-left text-[11px] font-semibold transition-all <?php echo $cIdx === 0 ? 'border-gold-500 bg-gold-500/10 text-white' : 'border-white/10 bg-obsidian text-gray-400 hover:border-gold-500/50'; ?>">
                                    <div class="truncate"><?php echo htmlspecialchars($c['name']); ?></div>
                                    <div class="text-[8px] text-gray-500 font-normal">Stock: <?php echo (int)($c['stock'] ?? 1); ?> un.</div>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- DESCRIPCIÓN DEL PRODUCTO -->
                <?php if (!empty($product['description'])): ?>
                    <div class="border-t border-white/10 pt-3 space-y-1">
                        <h3 class="text-[10px] font-bold text-gold-500 uppercase tracking-wider">Descripción</h3>
                        <p class="text-[11px] text-gray-300 leading-relaxed whitespace-pre-line font-light"><?php echo htmlspecialchars($product['description']); ?></p>
                    </div>
                <?php endif; ?>

                <!-- BOTÓN AGREGAR AL CARRITO -->
                <div class="pt-2">
                    <button onclick="addToCartCurrentProduct()" class="w-full py-3 rounded-xl gold-gradient-bg text-obsidian font-bold text-xs uppercase tracking-widest hover:brightness-110 shadow-md shadow-gold-500/20 active:scale-95 transition-all flex items-center justify-center gap-2">
                        <i class="fa-solid fa-bag-shopping text-xs"></i>
                        <span>Agregar al Carrito</span>
                    </button>
                </div>
            </div>

        </div>
    </main>

    <!-- MODAL DE ZOOM A PANTALLA COMPLETA -->
    <div id="image-zoom-modal" class="fixed inset-0 z-50 hidden bg-obsidian/95 backdrop-blur-md flex items-center justify-center p-2 sm:p-4">
        <button onclick="closeZoomModal()" class="absolute top-4 right-4 text-white hover:text-gold-500 text-2xl z-50 p-2"><i class="fa-solid fa-xmark"></i></button>
        <button onclick="navigateZoom(-1)" class="absolute left-2 sm:left-4 text-white hover:text-gold-500 text-2xl z-50 p-3"><i class="fa-solid fa-chevron-left"></i></button>
        <button onclick="navigateZoom(1)" class="absolute right-2 sm:right-4 text-white hover:text-gold-500 text-2xl z-50 p-3"><i class="fa-solid fa-chevron-right"></i></button>
        <div id="zoom-content" class="max-w-4xl max-h-[90vh] w-full h-full flex items-center justify-center p-2"></div>
    </div>

    <!-- CARRITO SIDEBAR COMPLETO INTEGRADO -->
    <div id="cart-drawer-overlay" onclick="toggleCartDrawer(false)" class="fixed inset-0 bg-obsidian/80 backdrop-blur-sm z-50 hidden opacity-0 transition-opacity duration-300"></div>
    
    <aside id="cart-drawer" class="fixed top-0 right-0 h-full w-full sm:w-[420px] bg-charcoal border-l border-gold-500/20 z-50 flex flex-col justify-between translate-x-full drawer-transition shadow-2xl overflow-hidden">
        <div class="p-3 sm:p-4 border-b border-white/10 bg-obsidian shrink-0 flex flex-col gap-2">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2.5">
                    <i class="fa-solid fa-bag-shopping text-gold-500 text-sm sm:text-base"></i>
                    <h3 class="font-brand text-xs sm:text-sm font-bold text-white tracking-wide">Carrito de Compras</h3>
                </div>
                <button onclick="toggleCartDrawer(false)" class="text-gray-400 hover:text-white p-1.5" aria-label="Cerrar Carrito"><i class="fa-solid fa-xmark text-base"></i></button>
            </div>
            <div class="flex items-center justify-between text-[9px] font-bold uppercase tracking-wider pt-1 border-t border-white/5">
                <div id="step-indicator-1" class="flex items-center gap-1.5 text-gold-500">
                    <span class="w-4 h-4 rounded-full bg-gold-500 text-obsidian flex items-center justify-center text-[9px] font-black">1</span>
                    <span>Productos</span>
                </div>
                <div class="h-[1px] bg-white/10 flex-1 mx-2"></div>
                <div id="step-indicator-2" class="flex items-center gap-1.5 text-gray-500">
                    <span class="w-4 h-4 rounded-full bg-charcoal border border-white/20 flex items-center justify-center text-[9px] font-bold">2</span>
                    <span>Pago y Datos</span>
                </div>
            </div>
        </div>

        <!-- PASO 1 CARRITO -->
        <div id="cart-step-1-content" class="flex-1 flex flex-col justify-between min-h-0 overflow-hidden">
            <div id="cart-items-container" class="p-3 overflow-y-auto flex-1 space-y-2 min-h-0"></div>
            <div class="p-3 border-t border-white/10 bg-obsidian space-y-2.5 shrink-0">
                <div class="flex justify-between text-xs font-bold text-white font-serif">
                    <span>Subtotal Productos:</span>
                    <span id="cart-step1-subtotal" class="text-gold-300">$ 0 ARS</span>
                </div>
                <button onclick="goToCartStep(2)" class="w-full py-3 rounded-xl gold-gradient-bg text-obsidian font-bold text-xs uppercase tracking-widest hover:brightness-110 shadow-md shadow-gold-500/20 flex items-center justify-center gap-2 active:scale-[0.98]">
                    <span>Confirmar</span>
                    <i class="fa-solid fa-arrow-right"></i>
                </button>
            </div>
        </div>

        <!-- PASO 2 CARRITO -->
        <div id="cart-step-2-content" class="flex-1 flex flex-col justify-between min-h-0 overflow-hidden hidden">
            <div class="p-3 overflow-y-auto flex-1 space-y-3 min-h-0">
                <button onclick="goToCartStep(1)" class="text-[10px] text-gold-500 font-bold uppercase tracking-wider flex items-center gap-1 hover:underline py-0.5">
                    <i class="fa-solid fa-arrow-left"></i> Modificar Productos
                </button>

                <div class="space-y-1.5 border-t border-white/10 pt-2">
                    <p class="text-[9px] font-bold text-gold-500 uppercase tracking-widest flex items-center gap-1">
                        <i class="fa-solid fa-credit-card"></i> Selección de Método de Pago
                    </p>
                    <div class="grid grid-cols-2 gap-1.5">
                        <label class="cursor-pointer border border-white/10 rounded-lg p-1.5 text-center bg-charcoal hover:border-gold-500 flex flex-col items-center justify-center text-[9px] text-gray-300 relative">
                            <input type="radio" name="payment-method" value="transferencia" onchange="updateCartUI()" checked class="accent-gold-500 hidden peer">
                            <div class="peer-checked:border-gold-500 peer-checked:bg-gold-500/20 w-full h-full p-1.5 rounded-md border border-transparent flex flex-col items-center justify-center">
                                <i class="fa-solid fa-building-columns text-gold-500 text-xs mb-0.5"></i>
                                <span class="font-bold">TRANSFERENCIA</span>
                            </div>
                        </label>
                        <label class="cursor-pointer border border-white/10 rounded-lg p-1.5 text-center bg-charcoal hover:border-gold-500 flex flex-col items-center justify-center text-[9px] text-gray-300 relative">
                            <input type="radio" name="payment-method" value="lista" onchange="updateCartUI()" class="accent-gold-500 hidden peer">
                            <div class="peer-checked:border-gold-500 peer-checked:bg-gold-500/20 w-full h-full p-1.5 rounded-md border border-transparent flex flex-col items-center justify-center">
                                <i class="fa-solid fa-credit-card text-blue-400 text-xs mb-0.5"></i>
                                <span class="font-bold">PRECIO LISTA</span>
                            </div>
                        </label>
                    </div>
                </div>

                <div class="space-y-1 text-[10px] bg-obsidian p-2.5 rounded-xl border border-white/10">
                    <div class="flex justify-between text-gray-400">
                        <span>Subtotal Lista:</span>
                        <span id="cart-subtotal" class="text-white font-medium">$ 0 ARS</span>
                    </div>
                    <div id="transfer-discount-row" class="hidden justify-between text-emerald-400 font-semibold">
                        <span>Descuento Transferencia:</span>
                        <span id="cart-transfer-saving">-$ 0 ARS</span>
                    </div>
                    <div class="flex justify-between text-xs font-bold text-white border-t border-white/10 pt-1 font-serif">
                        <span>TOTAL A PAGAR:</span>
                        <span id="cart-total" class="text-gold-300">$ 0 ARS</span>
                    </div>
                </div>

                <div class="space-y-1.5 pt-1 border-t border-white/10">
                    <p class="text-[9px] font-bold text-gold-500 uppercase tracking-widest">Datos para el Pedido</p>
                    <div class="grid grid-cols-1 gap-1.5">
                        <input type="text" id="customer-name" placeholder="Nombre completo *" class="w-full bg-charcoal border border-white/10 rounded-lg px-2.5 py-2 text-[10px] text-white focus:outline-none focus:border-gold-500">
                        <input type="text" id="customer-city" placeholder="Ciudad / Provincia *" class="w-full bg-charcoal border border-white/10 rounded-lg px-2.5 py-2 text-[10px] text-white focus:outline-none focus:border-gold-500">
                    </div>
                </div>
            </div>

            <div class="p-3 border-t border-white/10 bg-obsidian shrink-0">
                <button onclick="checkoutWhatsApp()" class="w-full py-3 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs uppercase tracking-widest transition-all flex items-center justify-center gap-2 shadow-md shadow-emerald-600/20 active:scale-[0.98]">
                    <i class="fa-brands fa-whatsapp text-base"></i>
                    <span>Completar por WhatsApp</span>
                </button>
            </div>
        </div>
    </aside>

    <footer class="bg-obsidian border-t border-white/10 py-6 mt-8">
        <div class="max-w-6xl mx-auto px-4 text-center text-[10px] sm:text-xs text-gray-500">
            &copy; 2026 CZSTORE Argentina. Todos los derechos reservados.
        </div>
    </footer>

    <div id="toast-container" class="fixed bottom-4 right-4 z-50 space-y-2 pointer-events-none"></div>

    <script>
        const productData = <?php echo json_encode($product); ?>;
        const catalog = <?php echo json_encode($catalog); ?>;
        const mediaItems = <?php echo json_encode($mediaItems); ?>;
        const whatsappNumber = <?php echo json_encode($serverWhatsapp); ?>;
        
        let cart = [];
        let selectedColor = productData.colors && productData.colors.length > 0 ? productData.colors[0].name : 'Estándar';
        let currentZoomIndex = 0;

        function sanitizeInput(str) {
            if (typeof str !== 'string') return '';
            return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
        }

        function formatARS(price) {
            return '$ ' + Math.round(Number(price) || 0).toLocaleString('es-AR') + ' ARS';
        }

        function showToast(message, type = "gold") {
            const container = document.getElementById('toast-container');
            if (!container) return;
            const toast = document.createElement('div');
            let bgClass = "bg-gold-500 text-black border-gold-300";
            if (type === "error") bgClass = "bg-red-600 text-white border-red-400";
            
            toast.className = `px-4 py-2.5 rounded-xl border text-xs font-bold shadow-xl flex items-center gap-2 pointer-events-auto ${bgClass}`;
            toast.innerHTML = `<i class="fa-solid fa-circle-check"></i> <span>${sanitizeInput(message)}</span>`;
            
            container.appendChild(toast);
            setTimeout(() => toast.remove(), 3000);
        }

        function scrollToSlide(idx) {
            const el = document.getElementById('slide-' + idx);
            if (el) el.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'start' });
        }

        function selectColor(colorName, btn) {
            selectedColor = colorName;
            document.querySelectorAll('.color-btn').forEach(b => {
                b.classList.remove('border-gold-500', 'bg-gold-500/10', 'text-white');
                b.classList.add('border-white/10', 'bg-obsidian', 'text-gray-400');
            });
            btn.classList.remove('border-white/10', 'bg-obsidian', 'text-gray-400');
            btn.classList.add('border-gold-500', 'bg-gold-500/10', 'text-white');
        }

        // ZOOM MODAL
        function openZoomModal(idx) {
            currentZoomIndex = idx;
            renderZoomContent();
            document.getElementById('image-zoom-modal').classList.remove('hidden');
        }

        function closeZoomModal() {
            document.getElementById('image-zoom-modal').classList.add('hidden');
        }

        function navigateZoom(dir) {
            currentZoomIndex += dir;
            if (currentZoomIndex < 0) currentZoomIndex = mediaItems.length - 1;
            if (currentZoomIndex >= mediaItems.length) currentZoomIndex = 0;
            renderZoomContent();
        }

        function renderZoomContent() {
            const container = document.getElementById('zoom-content');
            const url = mediaItems[currentZoomIndex];
            const isVideo = url.match(/\.(mp4|webm|mov|m4v)$/i) !== null;

            if (isVideo) {
                container.innerHTML = `<video src="${sanitizeInput(url)}" controls class="max-w-full max-h-full object-contain rounded-xl"></video>`;
            } else {
                container.innerHTML = `<img src="${sanitizeInput(url)}" class="max-w-full max-h-full object-contain rounded-xl">`;
            }
        }

        // CARRITO
        function saveCartToStorage() { localStorage.setItem('czstore_cart', JSON.stringify(cart)); }
        function loadCartFromStorage() {
            try { cart = JSON.parse(localStorage.getItem('czstore_cart')) || []; } catch(e) { cart = []; }
        }

        function addToCartCurrentProduct() {
            const existingIdx = cart.findIndex(i => i.id === productData.id && i.color === selectedColor);
            if (existingIdx !== -1) {
                cart[existingIdx].quantity += 1;
            } else {
                cart.push({ id: productData.id, color: selectedColor, quantity: 1 });
            }
            saveCartToStorage();
            updateCartUI();
            showToast("Producto agregado al carrito");
        }

        function toggleCartDrawer(open) {
            const drawer = document.getElementById('cart-drawer');
            const overlay = document.getElementById('cart-drawer-overlay');
            if (open) {
                overlay.classList.remove('hidden');
                setTimeout(() => overlay.classList.remove('opacity-0'), 10);
                drawer.classList.remove('translate-x-full');
            } else {
                drawer.classList.add('translate-x-full');
                overlay.classList.add('opacity-0');
                setTimeout(() => overlay.classList.add('hidden'), 300);
            }
        }

        function goToCartStep(step) {
            const step1 = document.getElementById('cart-step-1-content');
            const step2 = document.getElementById('cart-step-2-content');
            if (step === 1) {
                step1.classList.remove('hidden');
                step2.classList.add('hidden');
            } else {
                if (cart.length === 0) return showToast("El carrito está vacío", "error");
                step1.classList.add('hidden');
                step2.classList.remove('hidden');
            }
        }

        function updateCartQuantity(watchId, color, change) {
            const idx = cart.findIndex(item => item.id === watchId && item.color === color);
            if (idx !== -1) {
                cart[idx].quantity += change;
                if (cart[idx].quantity <= 0) cart.splice(idx, 1);
            }
            saveCartToStorage();
            updateCartUI();
        }

        function removeFromCart(watchId, color) {
            cart = cart.filter(item => !(item.id === watchId && item.color === color));
            saveCartToStorage();
            updateCartUI();
        }

        function getBasePrice(watch) {
            let base = Number(watch.price) || 0;
            if (watch.discountPercent && Number(watch.discountPercent) > 0) {
                base = base * (1 - Number(watch.discountPercent) / 100);
            }
            return base;
        }

        function getFinalPrice(watch, paymentMethod = 'transferencia') {
            let base = getBasePrice(watch);
            if (paymentMethod === 'transferencia' && watch.transferDiscountPercent && Number(watch.transferDiscountPercent) > 0) {
                base = base * (1 - Number(watch.transferDiscountPercent) / 100);
            }
            return base;
        }

        function getSelectedPaymentMethod() {
            const selected = document.querySelector('input[name="payment-method"]:checked');
            return selected ? selected.value : 'transferencia';
        }

        function updateCartUI() {
            const badgeCount = document.getElementById('cart-badge-count');
            const itemsContainer = document.getElementById('cart-items-container');
            const step1Subtotal = document.getElementById('cart-step1-subtotal');
            const cartSubtotal = document.getElementById('cart-subtotal');
            const cartTotal = document.getElementById('cart-total');
            const transferRow = document.getElementById('transfer-discount-row');
            const transferSaving = document.getElementById('cart-transfer-saving');

            const totalCount = cart.reduce((acc, item) => acc + item.quantity, 0);
            if (badgeCount) badgeCount.innerText = totalCount;

            if (!itemsContainer) return;

            if (cart.length === 0) {
                itemsContainer.innerHTML = `<div class="text-center py-12 text-xs text-gray-400">Tu carrito está vacío</div>`;
                if (step1Subtotal) step1Subtotal.innerText = "$ 0 ARS";
                if (cartSubtotal) cartSubtotal.innerText = "$ 0 ARS";
                if (cartTotal) cartTotal.innerText = "$ 0 ARS";
                if (transferRow) transferRow.classList.add('hidden');
                return;
            }

            const paymentMethod = getSelectedPaymentMethod();
            let baseSubtotal = 0;
            let finalTotal = 0;

            itemsContainer.innerHTML = "";

            cart.forEach(item => {
                const watch = catalog.find(w => w.id === item.id);
                if (!watch) return;

                const basePrice = getBasePrice(watch);
                const itemFinalPrice = getFinalPrice(watch, paymentMethod);

                baseSubtotal += basePrice * item.quantity;
                finalTotal += itemFinalPrice * item.quantity;

                const row = document.createElement('div');
                row.className = "p-2.5 rounded-xl bg-obsidian border border-white/10 flex items-center justify-between gap-2.5";
                row.innerHTML = `
                    <img src="${sanitizeInput(watch.image)}" class="w-11 h-11 object-cover rounded-lg bg-charcoal shrink-0">
                    <div class="min-w-0 flex-1">
                        <h4 class="text-xs font-bold text-white truncate">${sanitizeInput(watch.brand)} - ${sanitizeInput(watch.title)}</h4>
                        <span class="text-[10px] text-gray-400 block truncate">Color: <span class="text-gold-300 font-semibold">${sanitizeInput(item.color)}</span></span>
                        <span class="text-xs font-serif font-bold text-gold-500">${formatARS(basePrice)}</span>
                    </div>
                    <div class="flex items-center gap-1.5 shrink-0">
                        <div class="flex items-center border border-white/10 rounded-lg bg-charcoal">
                            <button onclick="updateCartQuantity('${item.id}', '${sanitizeInput(item.color)}', -1)" class="px-2 py-1 text-xs text-gray-400 hover:text-white">-</button>
                            <span class="px-1.5 py-1 text-xs font-bold text-white">${item.quantity}</span>
                            <button onclick="updateCartQuantity('${item.id}', '${sanitizeInput(item.color)}', 1)" class="px-2 py-1 text-xs text-gray-400 hover:text-white">+</button>
                        </div>
                        <button onclick="removeFromCart('${item.id}', '${sanitizeInput(item.color)}')" class="text-red-400 hover:text-red-300 p-1.5 text-xs" aria-label="Eliminar">
                            <i class="fa-solid fa-trash-can"></i>
                        </button>
                    </div>
                `;
                itemsContainer.appendChild(row);
            });

            const totalSaving = baseSubtotal - finalTotal;

            if (step1Subtotal) step1Subtotal.innerText = formatARS(baseSubtotal);
            if (cartSubtotal) cartSubtotal.innerText = formatARS(baseSubtotal);
            if (cartTotal) cartTotal.innerText = formatARS(finalTotal);

            if (paymentMethod === 'transferencia' && totalSaving > 0) {
                if (transferRow) transferRow.classList.remove('hidden');
                if (transferSaving) transferSaving.innerText = `-${formatARS(totalSaving)}`;
            } else {
                if (transferRow) transferRow.classList.add('hidden');
            }
        }

        function checkoutWhatsApp() {
            if (cart.length === 0) return showToast("El carrito está vacío", "error");

            const name = document.getElementById('customer-name').value.trim() || 'No especificado';
            const city = document.getElementById('customer-city').value.trim() || 'No especificada';

            const paymentMethod = getSelectedPaymentMethod();
            let baseSubtotal = 0;
            let finalTotal = 0;

            let msg = `¡Hola! Quisiera realizar una compra en CZSTORE.\n\n`;
            msg += `📋 *DETALLE DEL PEDIDO:*\n`;

            cart.forEach((item, index) => {
                const watch = catalog.find(w => w.id === item.id);
                if (!watch) return;

                const basePrice = getBasePrice(watch);
                const subtotalItem = basePrice * item.quantity;
                baseSubtotal += subtotalItem;

                msg += `${index + 1}. *${watch.brand} - ${watch.title}*\n`;
                msg += `   • Variación / Color: ${item.color}\n`;
                msg += `   • Cantidad: ${item.quantity}\n`;
                msg += `   • Precio Unitario Lista: ${formatARS(watch.price)}\n`;

                if (paymentMethod === 'transferencia' && watch.transferDiscountPercent > 0) {
                    const priceWithDesc = getFinalPrice(watch, 'transferencia');
                    msg += `   • Precio c/ Desc. (${watch.transferDiscountPercent}% OFF): ${formatARS(priceWithDesc)}\n`;
                    msg += `   • Subtotal Producto: ${formatARS(priceWithDesc * item.quantity)}\n\n`;
                    finalTotal += priceWithDesc * item.quantity;
                } else {
                    msg += `   • Subtotal Producto: ${formatARS(subtotalItem)}\n\n`;
                    finalTotal += subtotalItem;
                }
            });

            const totalSaving = baseSubtotal - finalTotal;

            msg += `-----------------------------------\n`;
            msg += `💳 *Método de Pago:* ${paymentMethod.toUpperCase()}\n`;
            msg += `💰 *Subtotal Lista:* ${formatARS(baseSubtotal)}\n`;
            
            if (paymentMethod === 'transferencia' && totalSaving > 0) {
                msg += `⚡ *Descuento Transferencia:* -${formatARS(totalSaving)}\n`;
                msg += `💵 *Subtotal con Descuento:* ${formatARS(finalTotal)}\n`;
            }

            msg += `🔥 *TOTAL FINAL A PAGAR:* ${formatARS(finalTotal)}\n\n`;
            msg += `👤 *DATOS DEL COMPRADOR:*\n`;
            msg += `• Nombre: ${name}\n`;
            msg += `• Ciudad / Provincia: ${city}`;

            const encodedMsg = encodeURIComponent(msg);
            const cleanPhone = whatsappNumber.replace(/[^0-9]/g, '');
            window.open(`https://wa.me/${cleanPhone}?text=${encodedMsg}`, '_blank');
        }

        window.addEventListener('DOMContentLoaded', () => {
            loadCartFromStorage();
            updateCartUI();
        });
    </script>
</body>
</html>
