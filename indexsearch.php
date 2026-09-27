<?php
// Configuración de codificación UTF-8 y prevención de caché
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

// ====== AUTENTICACIÓN MYSQL + SESIONES PHP SEGURAS ======
require_once __DIR__ . '/db_config.php';
startSecureSession();

$dataFile = __DIR__ . '/catalog.json';
$whatsappFile = __DIR__ . '/whatsapp.txt';
$textsFile = __DIR__ . '/site_texts.json';
$categoriesFile = __DIR__ . '/categories.json';
$uploadDir = __DIR__ . '/uploads/';

// Función para formatear las dos primeras letras con efecto dorado + glow y el resto en blanco + glow
function formatBrandWithGlow($text) {
    $cleanText = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    if (mb_strlen($cleanText) <= 2) {
        return '<span class="text-gold-500 drop-shadow-[0_0_10px_rgba(212,175,55,0.8)]">' . $cleanText . '</span>';
    }
    $goldPart = mb_substr($cleanText, 0, 2);
    $whitePart = mb_substr($cleanText, 2);
    return '<span class="text-gold-500 drop-shadow-[0_0_10px_rgba(212,175,55,0.8)]">' . $goldPart . '</span><span class="text-white drop-shadow-[0_0_8px_rgba(255,255,255,0.7)]">' . $whitePart . '</span>';
}

// 2. Subida de Archivos (Imágenes y Videos) — validación por SESIÓN PHP
if (isset($_FILES['media_file'])) {
    header('Content-Type: application/json; charset=utf-8');
    
    if (!isAdminSessionValid()) {
        echo json_encode(['success' => false, 'message' => 'Acceso de administrador no autorizado']);
        exit;
    }

    $file = $_FILES['media_file'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'mp4', 'webm', 'mov', 'm4v'];

    if (!in_array($ext, $allowed)) {
        echo json_encode(['success' => false, 'message' => 'Formato no permitido']);
        exit;
    }

    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $isVideo = in_array($ext, ['mp4', 'webm', 'mov', 'm4v']);
    $prefix = $isVideo ? 'video_' : 'cz_';
    $filename = $prefix . time() . '_' . rand(1000, 9999) . '.' . $ext;
    $targetPath = $uploadDir . $filename;

    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        echo json_encode(['success' => true, 'url' => 'uploads/' . $filename, 'isVideo' => $isVideo]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error al mover el archivo subido']);
    }
    exit;
}

// 3. Manejo de Textos Dinámicos
$defaultTexts = [
    "header_brand_name" => "CZSTORE",
    "brand_tagline" => "Exclusivos & Réplicas",
    "hero_badge" => "Colección 2026 • Envíos a todo el país",
    "hero_title" => "Elegancia Exclusiva y Detalles de Alta Gama",
    "hero_subtitle" => "Catálogo exclusivo de relojería de lujo. Explora nuestras ofertas especiales, descuentos por transferencia y stock actualizado.",
    "instagram_user" => "czstore_arg",
    "instagram_url" => "https://instagram.com/czstore_arg",
    "shipping_title" => "Envíos Gratis",
    "shipping_text" => "Envíos directos y seguros a todo el país",
    "whatsapp_title" => "Atención WhatsApp",
    "whatsapp_card_text" => "Respuesta rápida y atención personalizada",
    "instagram_title" => "Síguenos en Instagram",
    "instagram_card_text" => "Novedades y lanzamientos diarios en redes",
    "footer_brand_name" => "CZSTORE",
    "footer_tagline" => "Exclusivos & Réplicas",
    "footer_copyright" => "© 2026 CZSTORE Argentina. Precios expresados en Pesos Argentinos (ARS)."
];

if (file_exists($textsFile)) {
    $siteTexts = json_decode(file_get_contents($textsFile), true) ?: $defaultTexts;
    $siteTexts = array_merge($defaultTexts, $siteTexts);
} else {
    $siteTexts = $defaultTexts;
    file_put_contents($textsFile, json_encode($defaultTexts, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

// 4. Manejo de Categorías Dinámicas
$defaultCategories = ["Réplicas AAA+", "Ediciones Limitadas", "Lujo Clásico"];
if (file_exists($categoriesFile)) {
    $categoriesData = json_decode(file_get_contents($categoriesFile), true) ?: $defaultCategories;
} else {
    $categoriesData = $defaultCategories;
    file_put_contents($categoriesFile, json_encode($defaultCategories, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

// 5. Procesamiento de peticiones AJAX POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    
    $action = $input['action'] ?? '';

    // ---- login_check: valida usuario/contraseña contra MySQL con prepared statement ----
    if ($action === 'login_check') {
        $user = (string)($input['username'] ?? '');
        $pass = (string)($input['password'] ?? '');
        if (validateAdminCredentials($user, $pass)) {
            createAdminSession($user);
            echo json_encode(['success' => true, 'message' => 'Acceso concedido']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Credenciales incorrectas']);
        }
        exit;
    }

    // ---- session_check: devuelve si la sesión PHP sigue activa ----
    if ($action === 'session_check') {
        if (isAdminSessionValid()) {
            echo json_encode([
                'success'   => true,
                'logged_in' => true,
                'username'  => $_SESSION['admin_username'] ?? '',
            ]);
        } else {
            echo json_encode(['success' => true, 'logged_in' => false]);
        }
        exit;
    }

    // ---- logout: destruye la sesión admin ----
    if ($action === 'logout') {
        destroyAdminSession();
        echo json_encode(['success' => true, 'message' => 'Sesión cerrada']);
        exit;
    }

    // ---- resto de acciones: requieren sesión admin válida (no reenvío de credenciales) ----
    if (!isAdminSessionValid()) {
        echo json_encode(['success' => false, 'message' => 'Sesión expirada. Vuelve a iniciar sesión.']);
        exit;
    }
    
    if ($action === 'save_catalog') {
        $catalogData = $input['catalog'] ?? [];
        if (file_put_contents($dataFile, json_encode($catalogData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {
            echo json_encode(['success' => true, 'message' => 'Catálogo guardado con éxito']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error al guardar catalog.json']);
        }
        exit;
    }

    if ($action === 'save_categories') {
        $newCategories = $input['categories'] ?? [];
        if (file_put_contents($categoriesFile, json_encode(array_values($newCategories), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {
            echo json_encode(['success' => true, 'message' => 'Categorías actualizadas']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error al guardar categories.json']);
        }
        exit;
    }
    
    if ($action === 'save_whatsapp') {
        $number = $input['whatsapp'] ?? '5491100000000';
        file_put_contents($whatsappFile, $number);
        echo json_encode(['success' => true, 'message' => 'Número de WhatsApp actualizado']);
        exit;
    }

    if ($action === 'save_texts') {
        $newTexts = $input['texts'] ?? [];
        $merged = array_merge($siteTexts, $newTexts);
        if (file_put_contents($textsFile, json_encode($merged, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {
            echo json_encode(['success' => true, 'message' => 'Textos e información actualizados']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error al guardar site_texts.json']);
        }
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Acción no válida']);
    exit;
}

// Catálogo por defecto
$defaultWatches = [
    [
        "id" => "cz1",
        "brand" => "Rolex",
        "title" => "Submariner Date Kermit",
        "category" => "Réplicas AAA+",
        "isNovelty" => true,
        "promoText" => "",
        "discountPercent" => 0,
        "transferDiscountPercent" => 15,
        "price" => 195000,
        "image" => "https://images.unsplash.com/photo-1523275335684-37898b6baf30?auto=format&fit=crop&w=1200&q=90",
        "gallery" => [],
        "description" => "Edición especial con bisel cerámico verde. Calidad AAA+.",
        "colors" => [
            ["name" => "Verde Cerámico", "stock" => 5]
        ]
    ]
];

if (file_exists($dataFile)) {
    $serverCatalogJson = file_get_contents($dataFile);
    if (empty(trim($serverCatalogJson))) {
        $serverCatalogJson = json_encode($defaultWatches, JSON_UNESCAPED_UNICODE);
        file_put_contents($dataFile, $serverCatalogJson);
    }
} else {
    $serverCatalogJson = json_encode($defaultWatches, JSON_UNESCAPED_UNICODE);
    file_put_contents($dataFile, $serverCatalogJson);
}

$serverWhatsapp = file_exists($whatsappFile) ? trim(file_get_contents($whatsappFile)) : '5491100000000';
if (empty($serverWhatsapp)) $serverWhatsapp = '5491100000000';
?>
<!DOCTYPE html>
<html lang="es" class="dark scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?php echo htmlspecialchars($siteTexts['header_brand_name']); ?> | <?php echo htmlspecialchars($siteTexts['brand_tagline']); ?></title>
    
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
        .glass-panel { background: rgba(17, 19, 25, 0.96); backdrop-filter: blur(12px); border: 1px solid rgba(212, 175, 55, 0.2); }
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
        <div class="max-w-7xl mx-auto px-3 sm:px-6 lg:px-8 h-16 sm:h-20 flex items-center justify-between">
            <a href="index.php" class="flex items-center gap-2.5 sm:gap-3">
                <!-- LOGO CZSTORE -->
                <div class="w-12 h-12 sm:w-12 sm:h-12 rounded-full border border-gold-500/60 p-0.5 flex items-center justify-center bg-black/90 shadow-lg shadow-gold-500/10 hover:border-gold-500 transition-colors shrink-0 overflow-hidden">
                    <img src="logo.jpg" alt="CZStore" class="w-full h-full object-cover rounded-full">
                </div>
                <div>
                    <span id="display-header-brand" class="font-brand font-black text-xl sm:text-2xl tracking-[0.15em] block leading-none"><?php echo formatBrandWithGlow($siteTexts['header_brand_name']); ?></span>
                    <span id="display-brand-tagline" class="text-[10px] sm:text-[9px] font-semibold tracking-[0.15em] text-gray-400 uppercase block mt-0.5"><?php echo htmlspecialchars($siteTexts['brand_tagline']); ?></span>
                </div>
            </a>

            <div class="flex items-center gap-2 sm:gap-3">
                <button onclick="toggleCartDrawer(true)" class="relative flex items-center gap-2 px-3 py-2 sm:px-4 sm:py-2.5 rounded-full border border-gold-500/50 bg-gold-500/10 hover:bg-gold-500/20 text-gold-300 transition-all active:scale-95">
                    <i class="fa-solid fa-bag-shopping text-gold-500 text-xs sm:text-sm"></i>
                    <span class="text-xs font-bold tracking-wider hidden sm:inline">CARRITO</span>
                    <span id="cart-badge-count" class="bg-gold-500 text-obsidian text-[10px] font-black px-1.5 py-0.5 rounded-full min-w-[18px] text-center">0</span>
                </button>
            </div>
        </div>
    </header>

    <!-- HERO SECTION -->
    <section id="hero" class="relative overflow-hidden py-6 sm:py-12 border-b border-white/5">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10 text-center max-w-3xl space-y-3 sm:space-y-4">
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full border border-gold-500/30 bg-gold-500/10 text-gold-300 text-[10px] sm:text-xs font-bold tracking-widest uppercase">
                <span class="w-2 h-2 rounded-full bg-gold-500 animate-pulse"></span>
                <span id="display-hero-badge"><?php echo htmlspecialchars($siteTexts['hero_badge']); ?></span>
            </div>
            
            <h1 id="display-hero-title" class="font-serif text-2xl sm:text-5xl font-bold text-gold-500 leading-tight">
                <?php echo htmlspecialchars($siteTexts['hero_title']); ?>
            </h1>
            
            <p id="display-hero-subtitle" class="text-gray-400 text-xs sm:text-sm max-w-xl mx-auto font-light leading-relaxed">
                <?php echo htmlspecialchars($siteTexts['hero_subtitle']); ?>
            </p>

            <div class="pt-2">
                <a href="#catalog" class="px-6 py-2.5 rounded-full gold-gradient-bg text-obsidian font-bold text-xs tracking-widest uppercase hover:brightness-110 shadow-[0_4px_12px_rgba(212,175,55,0.45)] inline-block active:scale-95 transition-transform">
                    Ver Catálogo
                </a>
            </div>
        </div>
    </section>

    <!-- NOVEDADES Y PROMOCIONES -->
    <section id="featured-promos" class="py-5 sm:py-8 bg-charcoal/40 border-b border-gold-500/20">
        <div class="max-w-7xl mx-auto px-3 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between mb-3 sm:mb-4">
                <div class="flex items-center gap-2">
                    <i class="fa-solid fa-fire text-gold-500 text-base sm:text-xl"></i>
                    <div>
                        <h2 class="font-serif text-sm sm:text-xl font-bold text-white uppercase">Novedades & Promociones</h2>
                        <p class="text-[10px] sm:text-xs text-gray-400">Modelos destacados y ofertas exclusivas</p>
                    </div>
                </div>
            </div>

            <div id="featured-carousel" class="flex gap-3 sm:gap-4 overflow-x-auto scrollbar-none scroll-smooth pb-2 justify-start snap-x snap-mandatory"></div>
        </div>
    </section>

    <!-- CATÁLOGO PRINCIPAL -->
    <section id="catalog" class="py-6 sm:py-12 max-w-7xl mx-auto px-3 sm:px-6 lg:px-8 w-full">
        <div class="flex items-end justify-between mb-4 sm:mb-6 border-b border-white/10 pb-4">
            <div>
                <span class="text-gold-500 text-[10px] sm:text-xs font-bold tracking-[0.2em] uppercase block mb-1">Explorar Colección</span>
                <h2 class="font-serif text-lg sm:text-3xl font-bold text-white">Todos los Modelos</h2>
            </div>
        </div>

        <div class="flex items-center gap-2 overflow-x-auto pb-3 mb-4 sm:mb-6 scrollbar-none" id="category-filters-container"></div>

        <div id="watches-grid" class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3 sm:gap-6"></div>

        <div id="no-results-msg" class="hidden text-center py-12 space-y-3">
            <i class="fa-solid fa-clock-rotate-left text-3xl text-gray-600"></i>
            <p class="text-gray-400 font-serif text-sm">No se encontraron productos coincidentes.</p>
            <button onclick="resetFilters()" class="text-gold-500 text-xs font-bold uppercase tracking-wider underline">Mostrar Todo</button>
        </div>
    </section>

    <!-- CARRITO SIDEBAR -->
    <div id="cart-drawer-overlay" onclick="toggleCartDrawer(false)" class="fixed inset-0 bg-obsidian/80 backdrop-blur-sm z-50 hidden opacity-0 transition-opacity duration-300"></div>
    
    <aside id="cart-drawer" class="fixed top-0 right-0 h-full w-full sm:w-[420px] bg-charcoal border-l border-gold-500/20 z-50 flex flex-col justify-between translate-x-full drawer-transition shadow-2xl overflow-hidden">
        <div class="p-3 sm:p-4 border-b border-white/10 bg-obsidian shrink-0 flex flex-col gap-2">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2.5">
                    <i class="fa-solid fa-bag-shopping text-gold-500 text-base sm:text-lg"></i>
                    <h3 class="font-brand text-sm sm:text-base font-bold text-white tracking-wide">Carrito de Compras</h3>
                </div>
                <button onclick="toggleCartDrawer(false)" class="text-gray-400 hover:text-white p-2" aria-label="Cerrar Carrito"><i class="fa-solid fa-xmark text-lg"></i></button>
            </div>
            <div class="flex items-center justify-between text-[10px] font-bold uppercase tracking-wider pt-1 border-t border-white/5">
                <div id="step-indicator-1" class="flex items-center gap-1.5 text-gold-500">
                    <span class="w-5 h-5 rounded-full bg-gold-500 text-obsidian flex items-center justify-center text-[10px] font-black">1</span>
                    <span>Productos</span>
                </div>
                <div class="h-[1px] bg-white/10 flex-1 mx-3"></div>
                <div id="step-indicator-2" class="flex items-center gap-1.5 text-gray-500">
                    <span class="w-5 h-5 rounded-full bg-charcoal border border-white/20 flex items-center justify-center text-[10px] font-bold">2</span>
                    <span>Pago y Datos</span>
                </div>
            </div>
        </div>

        <div id="cart-step-1-content" class="flex-1 flex flex-col justify-between min-h-0 overflow-hidden">
            <div id="cart-items-container" class="p-3 sm:p-4 overflow-y-auto flex-1 space-y-2.5 min-h-0"></div>
            <div class="p-3 sm:p-4 border-t border-white/10 bg-obsidian space-y-3 shrink-0">
                <div class="flex justify-between text-sm font-bold text-white font-serif">
                    <span>Subtotal Productos:</span>
                    <span id="cart-step1-subtotal" class="text-gold-300">$ 0 ARS</span>
                </div>
                <button onclick="goToCartStep(2)" class="w-full py-3.5 rounded-xl gold-gradient-bg text-obsidian font-bold text-xs uppercase tracking-widest hover:brightness-110 shadow-lg shadow-gold-500/20 flex items-center justify-center gap-2 active:scale-[0.98]">
                    <span>Confirmar</span>
                    <i class="fa-solid fa-arrow-right"></i>
                </button>
            </div>
        </div>

        <div id="cart-step-2-content" class="flex-1 flex flex-col justify-between min-h-0 overflow-hidden hidden">
            <div class="p-3 sm:p-4 overflow-y-auto flex-1 space-y-3.5 min-h-0">
                <button onclick="goToCartStep(1)" class="text-[11px] text-gold-500 font-bold uppercase tracking-wider flex items-center gap-1.5 hover:underline py-1">
                    <i class="fa-solid fa-arrow-left"></i> Modificar Productos del Pedido
                </button>

                <div class="space-y-2 border-t border-white/10 pt-3">
                    <p class="text-[10px] font-bold text-gold-500 uppercase tracking-widest flex items-center gap-1">
                        <i class="fa-solid fa-credit-card"></i> Selección de Método de Pago
                    </p>
                    <div class="grid grid-cols-2 gap-2">
                        <label class="cursor-pointer border border-white/10 rounded-lg p-2 text-center bg-charcoal hover:border-gold-500 flex flex-col items-center justify-center text-[10px] text-gray-300 relative">
                            <input type="radio" name="payment-method" value="transferencia" onchange="updateCartUI()" checked class="accent-gold-500 hidden peer">
                            <div class="peer-checked:border-gold-500 peer-checked:bg-gold-500/20 w-full h-full p-2 rounded-md border border-transparent flex flex-col items-center justify-center">
                                <i class="fa-solid fa-building-columns text-gold-500 text-sm mb-1"></i>
                                <span class="font-bold">TRANSFERENCIA</span>
                            </div>
                        </label>
                        <label class="cursor-pointer border border-white/10 rounded-lg p-2 text-center bg-charcoal hover:border-gold-500 flex flex-col items-center justify-center text-[10px] text-gray-300 relative">
                            <input type="radio" name="payment-method" value="lista" onchange="updateCartUI()" class="accent-gold-500 hidden peer">
                            <div class="peer-checked:border-gold-500 peer-checked:bg-gold-500/20 w-full h-full p-2 rounded-md border border-transparent flex flex-col items-center justify-center">
                                <i class="fa-solid fa-credit-card text-blue-400 text-sm mb-1"></i>
                                <span class="font-bold">PRECIO LISTA</span>
                            </div>
                        </label>
                    </div>
                </div>

                <div class="space-y-1.5 text-[11px] bg-obsidian p-3 rounded-xl border border-white/10">
                    <div class="flex justify-between text-gray-400">
                        <span>Subtotal Lista:</span>
                        <span id="cart-subtotal" class="text-white font-medium">$ 0 ARS</span>
                    </div>
                    <div id="transfer-discount-row" class="hidden justify-between text-emerald-400 font-semibold">
                        <span>Descuento Transferencia:</span>
                        <span id="cart-transfer-saving">-$ 0 ARS</span>
                    </div>
                    <div class="flex justify-between text-sm font-bold text-white border-t border-white/10 pt-1.5 font-serif">
                        <span>TOTAL A PAGAR:</span>
                        <span id="cart-total" class="text-gold-300">$ 0 ARS</span>
                    </div>
                </div>

                <div class="space-y-2 pt-1 border-t border-white/10">
                    <p class="text-[10px] font-bold text-gold-500 uppercase tracking-widest">Datos para el Pedido</p>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                        <input type="text" id="customer-name" placeholder="Nombre completo *" class="w-full bg-charcoal border border-white/10 rounded-lg px-3 py-2.5 text-[11px] text-white focus:outline-none focus:border-gold-500">
                        <input type="text" id="customer-city" placeholder="Ciudad / Provincia *" class="w-full bg-charcoal border border-white/10 rounded-lg px-3 py-2.5 text-[11px] text-white focus:outline-none focus:border-gold-500">
                    </div>
                </div>
            </div>

            <div class="p-3 sm:p-4 border-t border-white/10 bg-obsidian shrink-0">
                <button onclick="checkoutWhatsApp()" class="w-full py-3.5 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs uppercase tracking-widest transition-all flex items-center justify-center gap-2 shadow-lg shadow-emerald-600/20 active:scale-[0.98]">
                    <i class="fa-brands fa-whatsapp text-lg"></i>
                    <span>Completar por WhatsApp</span>
                </button>
            </div>
        </div>
    </aside>

    <!-- MODAL LOGIN ADMIN -->
    <div id="admin-login-modal" class="fixed inset-0 z-50 hidden flex items-center justify-center p-4 bg-obsidian/90 backdrop-blur-md">
        <div class="glass-panel max-w-md w-full p-5 sm:p-6 rounded-3xl border border-gold-500/30 relative shadow-2xl space-y-4">
            <button onclick="closeAdminLoginModal()" class="absolute top-4 right-4 text-gray-400 hover:text-white p-2" aria-label="Cerrar"><i class="fa-solid fa-xmark text-lg"></i></button>

            <div class="text-center space-y-2">
                <div class="w-12 h-12 rounded-2xl border border-gold-500/40 bg-gold-500/10 text-gold-500 flex items-center justify-center mx-auto text-xl">
                    <i class="fa-solid fa-user-shield"></i>
                </div>
                <h3 class="font-brand text-base sm:text-lg font-bold text-white">Gestión CZSTORE</h3>
                <p class="text-xs text-gray-400">Ingreso de Administrador</p>
            </div>

            <form onsubmit="handleAdminLogin(event)" class="space-y-3">
                <div>
                    <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1">Usuario Admin</label>
                    <input type="text" id="admin-user" required placeholder="admin" class="w-full bg-charcoal border border-white/10 rounded-xl px-3.5 py-2.5 text-xs text-white focus:outline-none focus:border-gold-500">
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1">Contraseña</label>
                    <input type="password" id="admin-pass" required placeholder="••••••••" class="w-full bg-charcoal border border-white/10 rounded-xl px-3.5 py-2.5 text-xs text-white focus:outline-none focus:border-gold-500">
                </div>
                <button type="submit" class="w-full py-3 rounded-xl gold-gradient-bg text-obsidian font-bold text-xs uppercase tracking-widest hover:brightness-110 active:scale-95 transition-transform">
                    Ingresar al Panel
                </button>
            </form>
        </div>
    </div>

    <!-- PANEL ADMIN -->
    <div id="admin-dashboard-modal" class="fixed inset-0 z-50 hidden flex items-start sm:items-center justify-center p-2 sm:p-4 bg-obsidian/95 backdrop-blur-md overflow-y-auto">
        <div class="glass-panel max-w-5xl w-full my-2 sm:my-6 p-3 sm:p-6 rounded-2xl border border-gold-500/40 relative shadow-2xl space-y-4 max-h-[95vh] overflow-y-auto">
            
            <div class="sticky top-0 bg-charcoal/95 backdrop-blur-md p-3 -mx-3 -mt-3 sm:-mx-6 sm:-mt-6 mb-2 border-b border-white/10 flex items-center justify-between z-30">
                <div class="flex items-center gap-2">
                    <div class="w-8 h-8 rounded-lg border border-gold-500/40 bg-gold-500/10 text-gold-500 flex items-center justify-center">
                        <i class="fa-solid fa-sliders text-xs"></i>
                    </div>
                    <div>
                        <h3 class="font-brand text-xs sm:text-base font-bold text-white">Panel de Administración</h3>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <button onclick="logoutAdmin()" class="px-2.5 py-1.5 rounded-lg border border-red-500/30 bg-red-500/10 text-red-400 text-[10px] sm:text-xs font-bold">Salir</button>
                    <button onclick="closeAdminDashboard()" class="w-8 h-8 rounded-full bg-obsidian border border-white/20 text-gray-400 flex items-center justify-center"><i class="fa-solid fa-xmark"></i></button>
                </div>
            </div>

            <div class="bg-charcoal/90 p-3 rounded-xl border border-white/10 flex flex-col sm:flex-row items-start sm:items-center gap-2">
                <label for="admin-section-select" class="text-xs font-bold text-gold-500 uppercase">Editar Sección:</label>
                <select id="admin-section-select" onchange="switchAdminSection(this.value)" class="w-full bg-obsidian border border-gold-500/40 text-gold-300 text-xs font-bold rounded-lg px-3 py-2">
                    <option value="catalog">📦 Catálogos (Productos e Inventario)</option>
                    <option value="categories">🏷️ Gestión de Categorías</option>
                    <option value="texts">📝 Textos del Sitio (Header, Hero, Footer)</option>
                    <option value="contacts">📱 Tarjetas de Envíos, WhatsApp y Redes</option>
                </select>
            </div>

            <!-- SECCIÓN CATÁLOGOS -->
            <div id="section-admin-catalog" class="admin-section grid grid-cols-1 lg:grid-cols-12 gap-4">
                <div class="lg:col-span-6 bg-charcoal/80 p-3 sm:p-4 rounded-xl border border-white/10 space-y-3">
                    <h4 id="admin-form-title" class="font-serif text-xs font-bold text-gold-300 border-b border-white/10 pb-2">
                        <i class="fa-solid fa-plus-circle mr-1.5"></i>Agregar / Editar Reloj
                    </h4>
                    <form id="watch-form" onsubmit="handleSaveWatch(event)" class="space-y-3">
                        <input type="hidden" id="form-watch-id">
                        
                        <div class="grid grid-cols-2 gap-2 bg-gold-500/10 p-2 rounded-lg border border-gold-500/20">
                            <div class="flex items-center gap-2">
                                <input type="checkbox" id="form-novelty" class="accent-gold-500 w-4 h-4">
                                <label for="form-novelty" class="text-xs font-bold text-gold-300">Novedad</label>
                            </div>
                            <input type="text" id="form-promo-text" placeholder="Texto Promo" class="bg-obsidian border border-white/10 rounded px-2 py-1 text-[11px] text-emerald-400">
                        </div>

                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="block text-[10px] font-bold text-gray-400 uppercase">% Desc. Directo</label>
                                <input type="number" id="form-discount-percent" placeholder="0" class="w-full bg-obsidian border border-white/10 rounded px-2.5 py-1.5 text-xs text-white">
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-gray-400 uppercase">% Desc. Transf.</label>
                                <input type="number" id="form-transfer-discount" placeholder="15" class="w-full bg-obsidian border border-white/10 rounded px-2.5 py-1.5 text-xs text-white">
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="block text-[10px] font-bold text-gray-400 uppercase">Marca</label>
                                <input type="text" id="form-brand" required class="w-full bg-obsidian border border-white/10 rounded px-2.5 py-1.5 text-xs text-white">
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-gray-400 uppercase">Modelo / Título</label>
                                <input type="text" id="form-title" required class="w-full bg-obsidian border border-white/10 rounded px-2.5 py-1.5 text-xs text-white">
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="block text-[10px] font-bold text-gray-400 uppercase">Categoría</label>
                                <select id="form-category" required class="w-full bg-obsidian border border-white/10 rounded px-2.5 py-1.5 text-xs text-white"></select>
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-gray-400 uppercase">Precio Lista (ARS)</label>
                                <input type="number" id="form-price" required class="w-full bg-obsidian border border-white/10 rounded px-2.5 py-1.5 text-xs text-white">
                            </div>
                        </div>

                        <div class="space-y-1">
                            <label class="block text-[10px] font-bold text-gold-500 uppercase">Colores y Stock (Ej: Verde:5, Negro:2)</label>
                            <input type="text" id="form-colors-stock" required placeholder="Verde Cerámico:5, Negro Silver:2" class="w-full bg-obsidian border border-white/10 rounded px-2.5 py-1.5 text-xs text-white">
                        </div>

                        <div class="space-y-2 bg-obsidian/60 p-2.5 rounded-xl border border-white/10">
                            <label class="block text-[10px] font-bold text-gold-500 uppercase">Archivos Multimedia</label>
                            <div>
                                <label class="block text-[10px] text-gray-300">Imagen Principal:</label>
                                <input type="file" accept="image/*" onchange="uploadMainImage(this)" class="w-full text-xs text-gray-400">
                                <div id="main-img-preview-container" class="pt-1"></div>
                            </div>
                            <div class="border-t border-white/5 pt-2">
                                <label class="block text-[10px] text-gray-300">Archivos Adicionales (Imágenes / Videos):</label>
                                <input type="file" accept="image/*,video/*" multiple onchange="uploadExtraImages(this)" class="w-full text-xs text-gray-400">
                                <div id="extra-imgs-preview-container" class="flex flex-wrap gap-2 pt-1"></div>
                            </div>
                        </div>

                        <div>
                            <label class="block text-[10px] font-bold text-gray-400 uppercase">Descripción</label>
                            <textarea id="form-desc" rows="2" class="w-full bg-obsidian border border-white/10 rounded px-2.5 py-1.5 text-xs text-white resize-none"></textarea>
                        </div>

                        <div class="flex gap-2 pt-2">
                            <button type="submit" class="flex-1 py-2.5 rounded gold-gradient-bg text-obsidian font-bold text-xs uppercase">Guardar Reloj</button>
                            <button type="button" onclick="resetWatchForm()" class="px-3 py-2.5 border border-white/10 text-gray-400 text-xs rounded">Limpiar</button>
                        </div>
                    </form>
                </div>

                <div class="lg:col-span-6 bg-charcoal/80 p-3 sm:p-4 rounded-xl border border-white/10 space-y-3">
                    <h4 class="font-serif text-xs font-bold text-white uppercase border-b border-white/10 pb-2">Inventario (<span id="admin-inventory-count">0</span>)</h4>
                    <div class="max-h-[450px] overflow-y-auto space-y-2" id="admin-product-list"></div>
                </div>
            </div>

            <!-- SECCIÓN CATEGORÍAS -->
            <div id="section-admin-categories" class="admin-section hidden bg-charcoal/80 p-4 rounded-xl border border-white/10 space-y-4 max-w-xl mx-auto">
                <h4 class="font-serif text-sm font-bold text-gold-300 border-b border-white/10 pb-2">Administración de Categorías</h4>
                
                <div class="flex gap-2">
                    <input type="text" id="new-category-name" placeholder="Nueva Categoría..." class="flex-1 bg-obsidian border border-white/10 rounded px-3 py-2 text-xs text-white">
                    <button onclick="addNewCategory()" class="px-4 py-2 bg-gold-500 text-obsidian font-bold text-xs uppercase rounded">Agregar</button>
                </div>

                <div class="space-y-2 pt-2" id="admin-categories-list"></div>
            </div>

            <!-- SECCIÓN TEXTOS (HEADER, HERO, FOOTER) -->
            <div id="section-admin-texts" class="admin-section hidden bg-charcoal/80 p-4 rounded-xl border border-white/10 space-y-3 max-w-xl mx-auto">
                <h4 class="font-serif text-sm font-bold text-gold-300 border-b border-white/10 pb-2">Editar Textos de Header, Hero y Footer</h4>
                <form onsubmit="handleSaveSiteTexts(event)" class="space-y-3">
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="block text-[10px] font-bold text-gold-500 uppercase mb-1">Nombre de Marca Header</label>
                            <input type="text" id="admin-text-header-brand" value="<?php echo htmlspecialchars($siteTexts['header_brand_name']); ?>" class="w-full bg-obsidian border border-white/10 rounded px-3 py-2 text-xs text-white">
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-gold-500 uppercase mb-1">Subtítulo Marca (Tagline)</label>
                            <input type="text" id="admin-text-brand-tagline" value="<?php echo htmlspecialchars($siteTexts['brand_tagline']); ?>" class="w-full bg-obsidian border border-white/10 rounded px-3 py-2 text-xs text-white">
                        </div>
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1">Badge Hero</label>
                        <input type="text" id="admin-text-hero-badge" value="<?php echo htmlspecialchars($siteTexts['hero_badge']); ?>" class="w-full bg-obsidian border border-white/10 rounded px-3 py-2 text-xs text-white">
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1">Título Hero</label>
                        <input type="text" id="admin-text-hero-title" value="<?php echo htmlspecialchars($siteTexts['hero_title']); ?>" class="w-full bg-obsidian border border-white/10 rounded px-3 py-2 text-xs text-white">
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1">Subtítulo Hero</label>
                        <textarea id="admin-text-hero-subtitle" rows="3" class="w-full bg-obsidian border border-white/10 rounded px-3 py-2 text-xs text-white resize-none"><?php echo htmlspecialchars($siteTexts['hero_subtitle']); ?></textarea>
                    </div>
                    <div class="border-t border-white/10 pt-3 space-y-2">
                        <label class="block text-[10px] font-bold text-gold-500 uppercase mb-1">Textos del Footer</label>
                        <div class="grid grid-cols-2 gap-2">
                            <input type="text" id="admin-text-footer-brand" placeholder="Nombre Marca Footer" value="<?php echo htmlspecialchars($siteTexts['footer_brand_name']); ?>" class="w-full bg-obsidian border border-white/10 rounded px-3 py-2 text-xs text-white">
                            <input type="text" id="admin-text-footer-tagline" placeholder="Tagline Footer" value="<?php echo htmlspecialchars($siteTexts['footer_tagline']); ?>" class="w-full bg-obsidian border border-white/10 rounded px-3 py-2 text-xs text-white">
                        </div>
                        <input type="text" id="admin-text-footer-copyright" placeholder="Copyright..." value="<?php echo htmlspecialchars($siteTexts['footer_copyright']); ?>" class="w-full bg-obsidian border border-white/10 rounded px-3 py-2 text-xs text-white">
                    </div>
                    <button type="submit" class="w-full py-2.5 bg-gold-500 text-obsidian text-xs font-bold uppercase rounded">Guardar Textos</button>
                </form>
            </div>

            <!-- SECCIÓN TARJETAS DE CONTACTO, REDES Y ENVÍOS -->
            <div id="section-admin-contacts" class="admin-section hidden bg-charcoal/80 p-4 rounded-xl border border-white/10 space-y-4 max-w-xl mx-auto">
                <h4 class="font-serif text-sm font-bold text-gold-300 border-b border-white/10 pb-2">Editar Tarjetas de Envíos, WhatsApp e Instagram</h4>

                <form onsubmit="handleSaveContacts(event)" class="space-y-4">
                    <!-- Tarjeta Envíos -->
                    <div class="space-y-2 bg-obsidian/40 p-3 rounded-xl border border-white/5">
                        <label class="block text-[10px] font-bold text-gold-500 uppercase">🚚 Tarjeta de Envíos</label>
                        <input type="text" id="admin-shipping-title" value="<?php echo htmlspecialchars($siteTexts['shipping_title']); ?>" placeholder="Título Tarjeta Envíos" class="w-full bg-obsidian border border-white/10 rounded px-3 py-2 text-xs text-white font-bold">
                        <input type="text" id="admin-shipping-text" value="<?php echo htmlspecialchars($siteTexts['shipping_text']); ?>" placeholder="Texto Tarjeta Envíos" class="w-full bg-obsidian border border-white/10 rounded px-3 py-2 text-xs text-gray-300">
                    </div>

                    <!-- Tarjeta WhatsApp -->
                    <div class="space-y-2 bg-obsidian/40 p-3 rounded-xl border border-white/5">
                        <label class="block text-[10px] font-bold text-emerald-400 uppercase">💬 Tarjeta de WhatsApp</label>
                        <input type="text" id="admin-whatsapp-title" value="<?php echo htmlspecialchars($siteTexts['whatsapp_title']); ?>" placeholder="Título Tarjeta WhatsApp" class="w-full bg-obsidian border border-white/10 rounded px-3 py-2 text-xs text-white font-bold">
                        <input type="text" id="admin-whatsapp-num" value="<?php echo htmlspecialchars($serverWhatsapp); ?>" placeholder="Número (Sin + ni espacios)" class="w-full bg-obsidian border border-white/10 rounded px-3 py-2 text-xs text-white">
                        <input type="text" id="admin-whatsapp-text" value="<?php echo htmlspecialchars($siteTexts['whatsapp_card_text']); ?>" placeholder="Subtexto tarjeta WhatsApp" class="w-full bg-obsidian border border-white/10 rounded px-3 py-2 text-xs text-gray-300">
                    </div>

                    <!-- Tarjeta Instagram -->
                    <div class="space-y-2 bg-obsidian/40 p-3 rounded-xl border border-white/5">
                        <label class="block text-[10px] font-bold text-gold-500 uppercase">📸 Tarjeta de Instagram</label>
                        <input type="text" id="admin-insta-title" value="<?php echo htmlspecialchars($siteTexts['instagram_title']); ?>" placeholder="Título Tarjeta Instagram" class="w-full bg-obsidian border border-white/10 rounded px-3 py-2 text-xs text-white font-bold">
                        <input type="text" id="admin-insta-user" value="<?php echo htmlspecialchars($siteTexts['instagram_user']); ?>" placeholder="Usuario sin @" class="w-full bg-obsidian border border-white/10 rounded px-3 py-2 text-xs text-white">
                        <input type="text" id="admin-insta-url" value="<?php echo htmlspecialchars($siteTexts['instagram_url']); ?>" placeholder="https://..." class="w-full bg-obsidian border border-white/10 rounded px-3 py-2 text-xs text-white">
                        <input type="text" id="admin-insta-text" value="<?php echo htmlspecialchars($siteTexts['instagram_card_text']); ?>" placeholder="Subtexto tarjeta Instagram" class="w-full bg-obsidian border border-white/10 rounded px-3 py-2 text-xs text-gray-300">
                    </div>

                    <button type="submit" class="w-full py-2.5 bg-gold-500 text-obsidian text-xs font-bold uppercase rounded hover:bg-gold-300">Guardar Cambios en Tarjetas</button>
                </form>
            </div>

        </div>
    </div>

    <!-- FOOTER -->
    <footer class="bg-obsidian border-t border-white/10 py-8 sm:py-10 mt-8 sm:mt-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6 sm:space-y-8">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 sm:gap-6 pb-6 sm:pb-8 border-b border-white/10 text-center md:text-left">
                
                <!-- Tarjeta Envíos -->
                <div class="flex items-center justify-start gap-4 p-3.5 sm:p-4 rounded-2xl bg-charcoal/50 border border-gold-500/10">
                    <div class="w-10 h-10 sm:w-12 sm:h-12 rounded-xl bg-gold-500/10 border border-gold-500/30 flex items-center justify-center text-gold-500 text-xl sm:text-2xl shrink-0">
                        <i class="fa-solid fa-truck-fast"></i>
                    </div>
                    <div class="text-left">
                        <h4 id="display-shipping-title" class="text-white font-bold text-xs sm:text-sm tracking-wider uppercase"><?php echo htmlspecialchars($siteTexts['shipping_title']); ?></h4>
                        <p id="display-shipping-text" class="text-gray-400 text-[11px] font-light mt-0.5"><?php echo htmlspecialchars($siteTexts['shipping_text']); ?></p>
                    </div>
                </div>

                <!-- Tarjeta WhatsApp -->
                <div class="flex items-center justify-start gap-4 p-3.5 sm:p-4 rounded-2xl bg-charcoal/50 border border-emerald-500/10">
                    <div class="w-10 h-10 sm:w-12 sm:h-12 rounded-xl bg-emerald-500/10 border border-emerald-500/30 flex items-center justify-center text-emerald-400 text-xl sm:text-2xl shrink-0">
                        <i class="fa-brands fa-whatsapp"></i>
                    </div>
                    <div class="text-left">
                        <h4 id="display-whatsapp-title" class="text-white font-bold text-xs sm:text-sm tracking-wider uppercase"><?php echo htmlspecialchars($siteTexts['whatsapp_title']); ?></h4>
                        <a id="footer-whatsapp-link" href="https://wa.me/<?php echo preg_replace('/[^0-9]/', '', $serverWhatsapp); ?>" target="_blank" class="text-emerald-400 text-xs font-semibold hover:underline block">
                            +<span id="footer-whatsapp-num"><?php echo htmlspecialchars($serverWhatsapp); ?></span>
                        </a>
                        <p id="display-whatsapp-card-text" class="text-gray-400 text-[10px] font-light mt-0.5"><?php echo htmlspecialchars($siteTexts['whatsapp_card_text']); ?></p>
                    </div>
                </div>

                <!-- Tarjeta Instagram -->
                <div class="flex items-center justify-start gap-4 p-3.5 sm:p-4 rounded-2xl bg-charcoal/50 border border-gold-500/10">
                    <div class="w-10 h-10 sm:w-12 sm:h-12 rounded-xl bg-gold-500/10 border border-gold-500/30 flex items-center justify-center text-gold-500 text-xl sm:text-2xl shrink-0">
                        <i class="fa-brands fa-instagram"></i>
                    </div>
                    <div class="text-left">
                        <h4 id="display-instagram-title" class="text-white font-bold text-xs sm:text-sm tracking-wider uppercase"><?php echo htmlspecialchars($siteTexts['instagram_title']); ?></h4>
                        <a id="footer-instagram-link" href="<?php echo htmlspecialchars($siteTexts['instagram_url']); ?>" target="_blank" class="text-gold-500 text-xs font-semibold hover:underline block">
                            @<span id="footer-instagram-user"><?php echo htmlspecialchars($siteTexts['instagram_user']); ?></span>
                        </a>
                        <p id="display-instagram-card-text" class="text-gray-400 text-[10px] font-light mt-0.5"><?php echo htmlspecialchars($siteTexts['instagram_card_text']); ?></p>
                    </div>
                </div>

            </div>

            <div class="flex flex-col md:flex-row items-center justify-between gap-4">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-full border border-gold-500/50 p-0.5 flex items-center justify-center bg-black/90 shrink-0">
                        <svg viewBox="0 0 500 500" class="w-full h-full">
                            <circle cx="250" cy="250" r="238" fill="none" stroke="#D4AF37" stroke-width="6"/>
                            <circle cx="250" cy="250" r="226" fill="none" stroke="#D4AF37" stroke-width="3"/>
                            <polygon points="310,135 185,135 120,210 120,290 185,365 245,365 200,320 160,320 160,180 265,180" fill="#07080a" stroke="#D4AF37" stroke-width="8" stroke-linejoin="round"/>
                            <polygon points="225,185 380,185 210,325 365,325 320,365 170,365 340,225 180,225" fill="#07080a" stroke="#D4AF37" stroke-width="8" stroke-linejoin="round"/>
                        </svg>
                    </div>
                    <div>
                        <span id="display-footer-brand" class="font-brand font-bold text-xs sm:text-sm tracking-widest block"><?php echo formatBrandWithGlow($siteTexts['footer_brand_name']); ?></span>
                        <span id="display-footer-tagline" class="text-[8px] text-gray-500 uppercase tracking-widest block"><?php echo htmlspecialchars($siteTexts['footer_tagline']); ?></span>
                    </div>
                </div>
                <div class="flex items-center gap-4 text-[10px] sm:text-[11px] text-gray-500 font-light text-center">
                    <span id="display-footer-copyright"><?php echo htmlspecialchars($siteTexts['footer_copyright']); ?></span>
                    <button onclick="openAdminModal()" class="text-gray-500 hover:text-gold-500 transition-colors uppercase font-bold text-[10px] flex items-center gap-1">
                        <i class="fa-solid fa-user-shield"></i> Admin
                    </button>
                </div>
            </div>
        </div>
    </footer>

    <div id="toast-container" class="fixed bottom-4 right-4 z-50 space-y-2 pointer-events-none"></div>

    <script>
        const API_ENDPOINT = window.location.pathname;

        let catalog = <?php echo $serverCatalogJson; ?>;
        let categories = <?php echo json_encode($categoriesData); ?>;
        let whatsappNumber = <?php echo json_encode($serverWhatsapp); ?>;
        let siteTexts = <?php echo json_encode($siteTexts); ?>;
        
        let cart = []; 
        let currentFilter = "Todos";
        
        let isAdminLoggedIn = false;
        let adminUser = "";
        // adminPassword eliminado: la contraseña NUNCA se guarda en el navegador.
        // La sesión se valida en el servidor (cookie PHPSESSID HttpOnly).

        let currentMainImgUrl = "";
        let currentGalleryUrls = [];

        function sanitizeInput(str) {
            if (typeof str !== 'string') return '';
            return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
        }

        function formatARS(price) {
            return '$ ' + Math.round(Number(price) || 0).toLocaleString('es-AR') + ' ARS';
        }

        function isVideoUrl(url) {
            if (!url) return false;
            return url.match(/\.(mp4|webm|mov|m4v)$/i) !== null;
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

        function showToast(message, type = "gold") {
            const container = document.getElementById('toast-container');
            if (!container) return;
            const toast = document.createElement('div');
            let bgClass = "bg-gold-500 text-black border-gold-300";
            if (type === "error") bgClass = "bg-red-600 text-white border-red-400";
            if (type === "info") bgClass = "bg-blue-600 text-white border-blue-400";
            
            toast.className = `px-4 py-2.5 rounded-xl border text-xs font-bold shadow-xl flex items-center gap-2 pointer-events-auto ${bgClass}`;
            toast.innerHTML = `<i class="fa-solid fa-circle-info"></i> <span>${sanitizeInput(message)}</span>`;
            
            container.appendChild(toast);
            setTimeout(() => toast.remove(), 3000);
        }

        window.addEventListener('DOMContentLoaded', () => {
            checkAdminSession();
            loadCartFromStorage();
            renderCategoryFilters();
            renderCategorySelectOptions();
            renderFeaturedPromos();
            renderCatalog();
            updateCartUI();
        });

        function renderCategoryFilters() {
            const container = document.getElementById('category-filters-container');
            if (!container) return;

            const filterList = ["Todos", "Novedades", ...categories];
            container.innerHTML = filterList.map(cat => `
                <button onclick="filterCategory('${sanitizeInput(cat)}')" 
                    class="cat-btn ${cat === currentFilter ? 'bg-gold-500 text-obsidian border-gold-500' : 'bg-charcoal text-gray-300 border-white/10 hover:border-gold-500/50'} px-3.5 py-2 rounded-full border text-[11px] sm:text-xs font-bold uppercase transition-all whitespace-nowrap active:scale-95" 
                    data-cat="${sanitizeInput(cat)}">
                    ${sanitizeInput(cat)}
                </button>
            `).join('');
        }

        function renderCategorySelectOptions() {
            const select = document.getElementById('form-category');
            if (!select) return;
            select.innerHTML = categories.map(cat => `<option value="${sanitizeInput(cat)}">${sanitizeInput(cat)}</option>`).join('');
        }

        function filterCategory(cat) {
            currentFilter = cat;
            renderCategoryFilters();
            renderCatalog();
        }

        function resetFilters() {
            currentFilter = "Todos";
            renderCategoryFilters();
            renderCatalog();
        }

        function renderCatalog() {
            const grid = document.getElementById('watches-grid');
            const noRes = document.getElementById('no-results-msg');
            if (!grid) return;

            let filtered = catalog.filter(watch => {
                return (currentFilter === "Todos") || 
                       (currentFilter === "Novedades" && watch.isNovelty) || 
                       (watch.category === currentFilter);
            });

            if (filtered.length === 0) {
                grid.innerHTML = "";
                if (noRes) noRes.classList.remove('hidden');
                return;
            }

            if (noRes) noRes.classList.add('hidden');
            grid.innerHTML = "";

            filtered.forEach(watch => {
                const basePrice = getBasePrice(watch);
                const hasDiscount = (watch.discountPercent > 0);

                const card = document.createElement('div');
                card.className = "bg-charcoal border border-white/10 rounded-2xl p-2.5 sm:p-4 flex flex-col justify-between hover:border-gold-500/50 transition-all";
                
                card.innerHTML = `
                    <div>
                        <a href="product.php?id=${encodeURIComponent(watch.id)}" class="block relative w-full aspect-square rounded-xl overflow-hidden bg-obsidian mb-2.5 group">
                            <img src="${sanitizeInput(watch.image)}" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300" onerror="this.src='https://images.unsplash.com/photo-1523275335684-37898b6baf30'">
                            ${watch.isNovelty ? `<span class="absolute top-1.5 left-1.5 text-[8px] sm:text-[9px] font-black px-1.5 py-0.5 rounded-full uppercase text-emerald-400 drop-shadow-[0_0_8px_rgba(52,211,153,0.8)] bg-black/60 backdrop-blur-sm tracking-wider">${sanitizeInput(watch.promoText) || 'Nuevo'}</span>` : ''}
                            ${hasDiscount ? `<span class="absolute bottom-1.5 right-1.5 text-[9px] sm:text-[10px] font-black px-1.5 py-0.5 rounded-full text-red-500 drop-shadow-[0_0_10px_rgba(239,68,68,0.9)] bg-black/60 backdrop-blur-sm tracking-wider">${watch.discountPercent}% OFF</span>` : ''}
                        </a>

                        <span class="text-[9px] font-bold text-gold-500 uppercase block truncate">${sanitizeInput(watch.brand)}</span>
                        <a href="product.php?id=${encodeURIComponent(watch.id)}" class="font-serif text-xs sm:text-sm font-bold text-white hover:text-gold-500 block truncate mb-1">
                            ${sanitizeInput(watch.title)}
                        </a>

                        ${watch.transferDiscountPercent > 0 ? `
                            <div class="my-1">
                                <span class="text-[9px] sm:text-[10px] font-bold text-emerald-400 drop-shadow-[0_0_8px_rgba(52,211,153,0.6)] uppercase tracking-wider block truncate">
                                    <i class="fa-solid fa-bolt mr-0.5"></i>${watch.transferDiscountPercent}% OFF en transferencia
                                </span>
                            </div>
                        ` : ''}
                    </div>

                    <div class="pt-2 border-t border-white/5 space-y-2 mt-2">
                        <div class="flex items-baseline justify-between gap-1 flex-wrap">
                            <div class="flex items-baseline gap-1.5 flex-wrap">
                                <span class="text-xs sm:text-sm font-bold text-gold-300 font-serif">${formatARS(basePrice)}</span>
                                ${hasDiscount ? `<span class="text-[9px] sm:text-[10px] text-gray-500 line-through">${formatARS(watch.price)}</span>` : ''}
                            </div>
                        </div>

                        <a href="product.php?id=${encodeURIComponent(watch.id)}" class="w-full py-2 rounded-xl bg-obsidian border border-gold-500/40 hover:bg-gold-500 hover:text-obsidian text-gold-300 text-[10px] sm:text-[11px] font-bold uppercase transition-all flex items-center justify-center gap-1 active:scale-95">
                            <span>Ver Detalles</span>
                            <i class="fa-solid fa-arrow-right text-[9px]"></i>
                        </a>
                    </div>
                `;
                grid.appendChild(card);
            });
        }

        function renderFeaturedPromos() {
            const container = document.getElementById('featured-carousel');
            if (!container) return;

            const promos = catalog.filter(w => w.isNovelty || w.promoText || (w.discountPercent && w.discountPercent > 0));
            if (promos.length === 0) {
                document.getElementById('featured-promos').classList.add('hidden');
                return;
            }

            document.getElementById('featured-promos').classList.remove('hidden');
            container.innerHTML = promos.map(watch => {
                const hasDiscount = (watch.discountPercent > 0);
                const basePrice = getBasePrice(watch);
                return `
                <div class="w-[200px] sm:w-[260px] bg-charcoal border border-white/10 rounded-2xl p-2.5 sm:p-4 flex flex-col justify-between shrink-0 hover:border-gold-500/50 transition-all snap-start">
                    <div>
                        <a href="product.php?id=${encodeURIComponent(watch.id)}" class="block relative w-full aspect-square rounded-xl overflow-hidden bg-obsidian mb-2.5 group">
                            <img src="${sanitizeInput(watch.image)}" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300">
                            ${watch.isNovelty ? `<span class="absolute top-1.5 left-1.5 text-[8px] sm:text-[9px] font-black px-1.5 py-0.5 rounded-full uppercase text-emerald-400 drop-shadow-[0_0_8px_rgba(52,211,153,0.8)] bg-black/60 backdrop-blur-sm tracking-wider">${sanitizeInput(watch.promoText) || 'Nuevo'}</span>` : ''}
                            ${hasDiscount ? `<span class="absolute bottom-1.5 right-1.5 text-[9px] sm:text-[10px] font-black px-1.5 py-0.5 rounded-full text-red-500 drop-shadow-[0_0_10px_rgba(239,68,68,0.9)] bg-black/60 backdrop-blur-sm tracking-wider">${watch.discountPercent}% OFF</span>` : ''}
                        </a>

                        <span class="text-[9px] font-bold text-gold-500 uppercase block truncate">${sanitizeInput(watch.brand)}</span>
                        <a href="product.php?id=${encodeURIComponent(watch.id)}" class="font-serif text-xs sm:text-sm font-bold text-white hover:text-gold-500 block truncate mb-1">
                            ${sanitizeInput(watch.title)}
                        </a>

                        ${watch.transferDiscountPercent > 0 ? `
                            <div class="my-1">
                                <span class="text-[9px] sm:text-[10px] font-bold text-emerald-400 drop-shadow-[0_0_8px_rgba(52,211,153,0.6)] uppercase tracking-wider block truncate">
                                    <i class="fa-solid fa-bolt mr-0.5"></i>${watch.transferDiscountPercent}% OFF en transferencia
                                </span>
                            </div>
                        ` : ''}
                    </div>

                    <div class="pt-2 border-t border-white/5 space-y-2 mt-2">
                        <div class="flex items-baseline justify-between gap-1 flex-wrap">
                            <div class="flex items-baseline gap-1.5 flex-wrap">
                                <span class="text-xs sm:text-sm font-bold text-gold-300 font-serif">${formatARS(basePrice)}</span>
                                ${hasDiscount ? `<span class="text-[9px] sm:text-[10px] text-gray-500 line-through">${formatARS(watch.price)}</span>` : ''}
                            </div>
                        </div>

                        <a href="product.php?id=${encodeURIComponent(watch.id)}" class="w-full py-2 rounded-xl bg-obsidian border border-gold-500/40 hover:bg-gold-500 hover:text-obsidian text-gold-300 text-[10px] sm:text-[11px] font-bold uppercase transition-all flex items-center justify-center gap-1 active:scale-95">
                            <span>Ver Detalles</span>
                            <i class="fa-solid fa-arrow-right text-[9px]"></i>
                        </a>
                    </div>
                </div>
            `;}).join('');
        }

        // CARRITO
        function saveCartToStorage() { localStorage.setItem('czstore_cart', JSON.stringify(cart)); }
        function loadCartFromStorage() {
            try { cart = JSON.parse(localStorage.getItem('czstore_cart')) || []; } catch(e) { cart = []; }
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

        // CHECKOUT WHATSAPP
        function checkoutWhatsApp() {
            if (cart.length === 0) return showToast("El carrito está vacío", "error");

            const name = document.getElementById('customer-name').value.trim() || 'No especificado';
            const city = document.getElementById('customer-city').value.trim() || 'No especificada';

            const paymentMethod = getSelectedPaymentMethod();
            let baseSubtotal = 0;
            let finalTotal = 0;

            let msg = `¡Hola! Quisiera realizar una compra.\n\n`;
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

        // ADMIN FUNCTIONS
        async function checkAdminSession() {
            try {
                const res = await fetch(API_ENDPOINT, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'session_check' })
                }).then(r => r.json());
                if (res.success && res.logged_in) {
                    isAdminLoggedIn = true;
                    adminUser = res.username || '';
                }
            } catch(e) { /* ignore: sesión inválida */ }
        }

        function openAdminModal() {
            if (isAdminLoggedIn) openAdminDashboard();
            else document.getElementById('admin-login-modal').classList.remove('hidden');
        }

        function closeAdminLoginModal() { document.getElementById('admin-login-modal').classList.add('hidden'); }
        
        function openAdminDashboard() {
            renderAdminInventoryTable();
            renderAdminCategoriesList();
            renderMediaPreviews();
            document.getElementById('admin-dashboard-modal').classList.remove('hidden');
        }
        
        function closeAdminDashboard() { document.getElementById('admin-dashboard-modal').classList.add('hidden'); }
        
        async function logoutAdmin() {
            try {
                await fetch(API_ENDPOINT, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'logout' })
                }).then(r => r.json());
            } catch(e) { /* ignore */ }
            isAdminLoggedIn = false;
            adminUser = "";
            sessionStorage.removeItem('czstore_admin_user');
            sessionStorage.removeItem('czstore_admin_pass');
            closeAdminDashboard();
            showToast("Sesión cerrada", "info");
        }

        function switchAdminSection(val) {
            document.querySelectorAll('.admin-section').forEach(el => el.classList.add('hidden'));
            const target = document.getElementById('section-admin-' + val);
            if (target) target.classList.remove('hidden');
        }

        async function handleAdminLogin(e) {
            e.preventDefault();
            const u = document.getElementById('admin-user').value.trim();
            const p = document.getElementById('admin-pass').value.trim();

            try {
                const res = await fetch(API_ENDPOINT, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'login_check', username: u, password: p })
                }).then(r => r.json());

                if (res.success) {
                    isAdminLoggedIn = true;
                    adminUser = u;
                    // NO se guarda la contraseña en el navegador.
                    // NO se abre automáticamente el panel admin: el usuario debe pulsar "Admin" de nuevo.
                    closeAdminLoginModal();
                    showToast("Acceso concedido. Pulsa Admin para abrir el panel.", "gold");
                } else {
                    showToast(res.message || "Credenciales incorrectas", "error");
                }
            } catch(e) {
                showToast("Error al conectar con el servidor", "error");
            }
        }

        async function uploadFileToServer(file) {
            const formData = new FormData();
            formData.append('media_file', file);
            // No se envían credenciales: se valida la sesión PHP vía cookie.
            try {
                return await fetch(API_ENDPOINT, { method: 'POST', body: formData }).then(r => r.json());
            } catch(e) {
                return { success: false, message: "Error al enviar archivo" };
            }
        }

        async function uploadMainImage(input) {
            if (!input.files || !input.files[0]) return;
            showToast("Subiendo imagen...", "info");
            const res = await uploadFileToServer(input.files[0]);
            if (res.success) {
                currentMainImgUrl = res.url;
                renderMediaPreviews();
                showToast("Imagen cargada", "gold");
            } else {
                showToast(res.message, "error");
            }
            input.value = "";
        }

        async function uploadExtraImages(input) {
            if (!input.files || !input.files.length) return;
            showToast("Subiendo archivos...", "info");
            for (let f of input.files) {
                const res = await uploadFileToServer(f);
                if (res.success) currentGalleryUrls.push(res.url);
            }
            renderMediaPreviews();
            showToast("Archivos subidos", "gold");
            input.value = "";
        }

        function removeMainImage() {
            currentMainImgUrl = "";
            renderMediaPreviews();
        }

        function removeGalleryMedia(index) {
            currentGalleryUrls.splice(index, 1);
            renderMediaPreviews();
        }

        function renderMediaPreviews() {
            const m = document.getElementById('main-img-preview-container');
            const e = document.getElementById('extra-imgs-preview-container');
            if (m) {
                m.innerHTML = currentMainImgUrl ? `
                    <div class="relative w-14 h-14 rounded overflow-hidden border border-gold-500 mt-1">
                        <img src="${sanitizeInput(currentMainImgUrl)}" class="w-full h-full object-cover">
                        <button type="button" onclick="removeMainImage()" class="absolute top-0 right-0 bg-red-600 text-white text-[9px] w-4 h-4 flex items-center justify-center">×</button>
                    </div>` : '<span class="text-[10px] text-gray-500 italic">Sin imagen principal</span>';
            }
            if (e) {
                e.innerHTML = currentGalleryUrls.length ? currentGalleryUrls.map((url, idx) => `
                    <div class="relative w-12 h-12 rounded overflow-hidden border border-white/20">
                        ${isVideoUrl(url) ? `<video src="${sanitizeInput(url)}" class="w-full h-full object-cover"></video>` : `<img src="${sanitizeInput(url)}" class="w-full h-full object-cover">`}
                        <button type="button" onclick="removeGalleryMedia(${idx})" class="absolute top-0 right-0 bg-red-600 text-white text-[9px] w-4 h-4 flex items-center justify-center">×</button>
                    </div>`).join('') : '<span class="text-[10px] text-gray-500 italic">Sin extras</span>';
            }
        }

        // GESTIÓN DE CATEGORÍAS
        function renderAdminCategoriesList() {
            const container = document.getElementById('admin-categories-list');
            if (!container) return;
            container.innerHTML = categories.map((cat, idx) => `
                <div class="flex items-center gap-2 p-2 bg-obsidian border border-white/10 rounded-lg">
                    <input type="text" value="${sanitizeInput(cat)}" onchange="updateCategoryName(${idx}, this.value)" class="flex-1 bg-transparent text-xs text-white border-b border-transparent focus:border-gold-500 focus:outline-none px-1 py-0.5">
                    <button onclick="deleteCategory(${idx})" class="text-red-400 hover:text-red-300 text-xs px-2 py-1"><i class="fa-solid fa-trash"></i></button>
                </div>
            `).join('');
        }

        async function addNewCategory() {
            const input = document.getElementById('new-category-name');
            const val = input.value.trim();
            if (!val) return showToast("Escribe un nombre de categoría", "error");
            if (categories.includes(val)) return showToast("La categoría ya existe", "error");
            
            categories.push(val);
            if (await saveCategoriesToServer()) {
                input.value = "";
                renderAdminCategoriesList();
                renderCategoryFilters();
                renderCategorySelectOptions();
                showToast("Categoría agregada", "gold");
            }
        }

        async function updateCategoryName(index, newName) {
            newName = newName.trim();
            if (!newName) return showToast("El nombre no puede estar vacío", "error");
            
            const oldName = categories[index];
            categories[index] = newName;
            
            catalog.forEach(watch => {
                if (watch.category === oldName) watch.category = newName;
            });

            await saveCatalogToServer();
            if (await saveCategoriesToServer()) {
                renderCategoryFilters();
                renderCategorySelectOptions();
                renderCatalog();
                showToast("Categoría actualizada", "gold");
            }
        }

        async function deleteCategory(index) {
            if (!confirm("¿Eliminar esta categoría? Los productos asociados se mantendrán en el catálogo.")) return;
            categories.splice(index, 1);
            if (await saveCategoriesToServer()) {
                renderAdminCategoriesList();
                renderCategoryFilters();
                renderCategorySelectOptions();
                showToast("Categoría eliminada", "gold");
            }
        }

        async function saveCategoriesToServer() {
            try {
                const res = await fetch(API_ENDPOINT, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'save_categories', categories })
                }).then(r => r.json());
                return res.success;
            } catch(e) {
                showToast("Error al guardar categorías", "error");
                return false;
            }
        }

        async function handleSaveWatch(e) {
            e.preventDefault();
            const id = document.getElementById('form-watch-id').value.trim();
            const isNovelty = document.getElementById('form-novelty').checked;
            const promoText = document.getElementById('form-promo-text').value.trim();
            const discountPercent = parseFloat(document.getElementById('form-discount-percent').value) || 0;
            const transferDiscountPercent = parseFloat(document.getElementById('form-transfer-discount').value) || 0;
            const brand = document.getElementById('form-brand').value.trim();
            const title = document.getElementById('form-title').value.trim();
            const category = document.getElementById('form-category').value.trim();
            const price = parseFloat(document.getElementById('form-price').value) || 0;
            const colorsRaw = document.getElementById('form-colors-stock').value.trim();
            const description = document.getElementById('form-desc').value.trim();

            let colorsArr = [];
            if (colorsRaw) {
                colorsArr = colorsRaw.split(',').map(item => {
                    const parts = item.split(':');
                    return { name: parts[0] ? parts[0].trim() : 'Único', stock: parts[1] ? parseInt(parts[1].trim(), 10) || 1 : 1 };
                });
            } else {
                colorsArr = [{ name: 'Estándar', stock: 1 }];
            }

            const watchData = {
                id: id || ('cz_' + Date.now()),
                brand, title, category, price, isNovelty, promoText,
                discountPercent, transferDiscountPercent, colors: colorsArr,
                image: currentMainImgUrl || 'https://images.unsplash.com/photo-1523275335684-37898b6baf30',
                gallery: currentGalleryUrls, description
            };

            if (id) {
                const idx = catalog.findIndex(w => w.id === id);
                if (idx !== -1) catalog[idx] = watchData;
            } else {
                catalog.push(watchData);
            }

            const saved = await saveCatalogToServer();
            if (saved) {
                resetWatchForm();
                renderCategoryFilters();
                renderCatalog();
                renderFeaturedPromos();
                renderAdminInventoryTable();
                showToast(id ? "Reloj actualizado" : "Reloj guardado", "gold");
            }
        }

        function editWatch(id) {
            const watch = catalog.find(w => w.id === id);
            if (!watch) return;

            const titleEl = document.getElementById('admin-form-title');
            if (titleEl) titleEl.innerHTML = `<i class="fa-solid fa-pen-to-square mr-1.5"></i>Editando: ${sanitizeInput(watch.title)}`;

            document.getElementById('form-watch-id').value = watch.id;
            document.getElementById('form-novelty').checked = !!watch.isNovelty;
            document.getElementById('form-promo-text').value = watch.promoText || '';
            document.getElementById('form-discount-percent').value = watch.discountPercent || 0;
            document.getElementById('form-transfer-discount').value = watch.transferDiscountPercent || 0;
            document.getElementById('form-brand').value = watch.brand || '';
            document.getElementById('form-title').value = watch.title || '';
            document.getElementById('form-category').value = watch.category || categories[0] || '';
            document.getElementById('form-price').value = watch.price || 0;
            document.getElementById('form-colors-stock').value = (watch.colors || []).map(c => `${c.name}:${c.stock}`).join(', ');
            document.getElementById('form-desc').value = watch.description || '';

            currentMainImgUrl = watch.image || '';
            currentGalleryUrls = Array.isArray(watch.gallery) ? [...watch.gallery] : [];
            renderMediaPreviews();
        }

        async function deleteWatch(id) {
            if (!confirm("¿Eliminar este reloj?")) return;
            catalog = catalog.filter(w => w.id !== id);
            if (await saveCatalogToServer()) {
                renderCategoryFilters();
                renderCatalog();
                renderAdminInventoryTable();
                showToast("Reloj eliminado", "gold");
            }
        }

        function resetWatchForm() {
            const titleEl = document.getElementById('admin-form-title');
            if (titleEl) titleEl.innerHTML = `<i class="fa-solid fa-plus-circle mr-1.5"></i>Agregar / Editar Reloj`;

            document.getElementById('form-watch-id').value = '';
            document.getElementById('watch-form').reset();
            currentMainImgUrl = "";
            currentGalleryUrls = [];
            renderMediaPreviews();
        }

        function renderAdminInventoryTable() {
            const list = document.getElementById('admin-product-list');
            if (!list) return;
            document.getElementById('admin-inventory-count').innerText = catalog.length;
            list.innerHTML = catalog.map(w => `
                <div class="p-2 bg-obsidian border border-white/5 rounded-xl flex items-center justify-between gap-2">
                    <div class="flex items-center gap-2 truncate">
                        <img src="${sanitizeInput(w.image)}" class="w-8 h-8 object-cover rounded bg-charcoal">
                        <span class="text-xs font-bold text-white truncate">${sanitizeInput(w.brand)} ${sanitizeInput(w.title)}</span>
                    </div>
                    <div class="flex gap-1 shrink-0">
                        <button onclick="editWatch('${w.id}')" class="p-1.5 rounded bg-gold-500/10 text-gold-300 text-xs" title="Editar"><i class="fa-solid fa-pen"></i></button>
                        <button onclick="deleteWatch('${w.id}')" class="p-1.5 rounded bg-red-500/10 text-red-400 text-xs" title="Eliminar"><i class="fa-solid fa-trash"></i></button>
                    </div>
                </div>
            `).join('');
        }

        async function saveCatalogToServer() {
            try {
                const res = await fetch(API_ENDPOINT, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'save_catalog', catalog: catalog })
                }).then(r => r.json());
                return res.success;
            } catch(e) {
                showToast("Error al guardar en el servidor", "error");
                return false;
            }
        }

        async function handleSaveSiteTexts(e) {
            e.preventDefault();
            const texts = {
                header_brand_name: document.getElementById('admin-text-header-brand').value.trim(),
                brand_tagline: document.getElementById('admin-text-brand-tagline').value.trim(),
                hero_badge: document.getElementById('admin-text-hero-badge').value.trim(),
                hero_title: document.getElementById('admin-text-hero-title').value.trim(),
                hero_subtitle: document.getElementById('admin-text-hero-subtitle').value.trim(),
                footer_brand_name: document.getElementById('admin-text-footer-brand').value.trim(),
                footer_tagline: document.getElementById('admin-text-footer-tagline').value.trim(),
                footer_copyright: document.getElementById('admin-text-footer-copyright').value.trim()
            };
            const res = await fetch(API_ENDPOINT, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'save_texts', texts })
            }).then(r => r.json());

            if (res.success) {
                showToast("Textos guardados", "gold");
                setTimeout(() => location.reload(), 1000);
            } else {
                showToast(res.message, "error");
            }
        }

        async function handleSaveContacts(e) {
            e.preventDefault();
            const waNum = document.getElementById('admin-whatsapp-num').value.trim();
            const texts = {
                shipping_title: document.getElementById('admin-shipping-title').value.trim(),
                shipping_text: document.getElementById('admin-shipping-text').value.trim(),
                whatsapp_title: document.getElementById('admin-whatsapp-title').value.trim(),
                whatsapp_card_text: document.getElementById('admin-whatsapp-text').value.trim(),
                instagram_title: document.getElementById('admin-insta-title').value.trim(),
                instagram_user: document.getElementById('admin-insta-user').value.trim(),
                instagram_url: document.getElementById('admin-insta-url').value.trim(),
                instagram_card_text: document.getElementById('admin-insta-text').value.trim()
            };

            await fetch(API_ENDPOINT, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'save_whatsapp', whatsapp: waNum })
            });

            const res = await fetch(API_ENDPOINT, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'save_texts', texts })
            }).then(r => r.json());

            if (res.success) {
                showToast("Información guardada con éxito", "gold");
                setTimeout(() => location.reload(), 1000);
            } else {
                showToast(res.message, "error");
            }
        }
    </script>
</body>
</html>
