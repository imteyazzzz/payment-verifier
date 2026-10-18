<?php
// Scan the current directory
$files = scandir(__DIR__);
$filtered_files = [];
$categories = [];

// Filter out '.' , '..' and the index.php file itself
foreach ($files as $file) {
    if ($file !== '.' && $file !== '..' && $file !== basename(__FILE__) && !is_dir($file)) {
        $filtered_files[] = $file;
        
        $ext = pathinfo($file, PATHINFO_EXTENSION);
        $cat_name = $ext === 'php' ? 'Guides' : ($ext ? strtoupper($ext) : 'Other');
        if (!in_array($cat_name, $categories)) {
            $categories[] = $cat_name;
        }
    }
}

// Function to extract a description from the file's content
function getFileDescription($filepath) {
    $content = file_get_contents($filepath);
    if (preg_match_all('/<p[^>]*>(.*?)<\/p>/is', $content, $matches)) {
        foreach ($matches[1] as $p) {
            $stripped = trim(strip_tags($p));
            if (strlen($stripped) > 40) {
                return strlen($stripped) > 130 ? substr($stripped, 0, 127) . '...' : $stripped;
            }
        }
    }
    return "Explore this comprehensive guide on integrations and backend logic.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ShipMyCode - Developer Resources</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap');
        
        :root {
            /* Palette: Light, Orange, Green */
            --bg-black: #f7f7f8;
            --bg-surface: #ffffff;
            --text-main: #0f0f10;
            --text-muted: #6b6b70;
            --brand-orange: #ff6b00;
            --brand-green: #00b85e;
            --border-color: #e2e2e5;
            --radius-lg: 16px;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }
a,
button {
  outline: none;
  -webkit-tap-highlight-color: transparent;
}

a:hover,
button:hover,
a:focus,
button:focus {
  outline: none;
  box-shadow: none;
}

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--bg-black);
            color: var(--text-main);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            overflow-x: hidden;
            font-size: 14px;
        }

        /* --- Animations --- */
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        @keyframes glowPulse {
            0% { box-shadow: 0 0 10px rgba(255, 107, 0, 0.08); }
            50% { box-shadow: 0 0 20px rgba(0, 184, 94, 0.12); }
            100% { box-shadow: 0 0 10px rgba(255, 107, 0, 0.08); }
        }

        /* Fixed Animation Visibility Bug */
        .animate-up {
            animation: fadeInUp 0.6s cubic-bezier(0.16, 1, 0.3, 1) both;
        }

        /* --- Full Curved Navbar (Floating Pill) --- */
        .navbar {
            background-color: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid var(--border-color);
            border-radius: 9999px; /* Fully curved edges */
            padding: 12px 28px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 16px; /* Floating space from top */
            margin: 0 auto;
            width: calc(100% - 32px); /* Margin on mobile */
            max-width: 1000px;
            z-index: 50;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.05);
        }

        .logo-img {
            height: 40px;
            width: auto;
            object-fit: contain;
            transition: transform 0.3s ease;
        }
        .logo-img:hover { transform: scale(1.05); }

        .logo-text-fallback {
            font-size: 20px;
            font-weight: 800;
            letter-spacing: -0.5px;
            display: flex;
            align-items: center;
            text-decoration: none;
        }
        .logo-text-fallback .t1 { color: var(--brand-orange); }
        .logo-text-fallback .t2 { color: var(--brand-green); }

        .menu-btn {
            background: transparent;
            border: 1px solid var(--border-color);
            border-radius: 9999px; /* Matched fully curved UI */
            padding: 8px 14px;
            cursor: pointer;
            color: var(--text-main);
            transition: all 0.3s ease;
            display: flex;
        }
        .menu-btn:hover { border-color: var(--brand-orange); color: var(--brand-orange); }
        .menu-btn svg { width: 22px; height: 22px; stroke: currentColor; stroke-width: 2; }

        /* --- Full Screen Menu --- */
        .slider-menu {
            position: fixed; inset: 0;
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(15px);
            z-index: 100;
            display: flex; flex-direction: column;
            justify-content: center; align-items: center;
            transform: translateX(100%);
            transition: transform 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .slider-menu.active { transform: translateX(0); }
        .close-btn {
            position: absolute; top: 24px; right: 24px;
            background: transparent; border: none;
            width: 40px; height: 40px; color: var(--text-main); font-size: 32px; cursor: pointer;
            transition: 0.3s;
        }
        .close-btn:hover { color: var(--brand-orange); transform: rotate(90deg); }
        
        .slider-menu a {
            color: var(--text-main); 
            font-size: 32px;
            font-weight: 800; 
            text-decoration: none;
            margin: 14px 0; 
            transition: 0.3s;
            letter-spacing: -0.5px;
        }
        .slider-menu a:hover { color: var(--brand-green); transform: scale(1.1); }

        /* --- Hero Section --- */
        .hero {
            padding: 70px 20px 32px;
            text-align: center;
            max-width: 800px;
            margin: 0 auto;
            position: relative;
        }
        .hero::before {
            content: ''; position: absolute; top: 50%; left: 50%;
            transform: translate(-50%, -50%);
            width: 300px; height: 300px;
            background: radial-gradient(circle, rgba(255,107,0,0.12) 0%, rgba(0,184,94,0.08) 50%, rgba(247,247,248,0) 100%);
            filter: blur(40px); z-index: -1; pointer-events: none;
        }
        
        .hero h1 {
            font-size: 38px;
            font-weight: 800;
            line-height: 1.15;
            margin-bottom: 14px;
            letter-spacing: -1px;
        }
        .hero h1 span {
            background: linear-gradient(90deg, var(--brand-orange), var(--brand-green));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .hero p {
            font-size: 14px;
            color: var(--text-muted);
            line-height: 1.6;
            max-width: 600px;
            margin: 0 auto;
        }

        /* --- Main Layout --- */
        .container {
            padding: 0 20px 60px;
            max-width: 1000px;
            margin: 0 auto;
            flex: 1;
            width: 100%;
        }

        /* --- Search & Filters --- */
        .tools-card {
            background: var(--bg-surface);
            border-radius: 24px;
            padding: 22px;
            margin-bottom: 32px;
            border: 1px solid var(--border-color);
            box-shadow: 0 4px 16px rgba(0,0,0,0.04);
            position: relative;
            z-index: 10;
        }

        .search-wrapper { position: relative; width: 100%; margin-bottom: 14px; }

        .search-input {
            width: 100%;
            padding: 12px 44px 12px 16px;
            border-radius: 12px;
            border: 2px solid var(--border-color);
            font-size: 14px;
            font-family: inherit;
            color: var(--text-main);
            background: var(--bg-black);
            outline: none;
            transition: all 0.3s ease;
        }
        .search-input:focus {
            border-color: var(--brand-orange);
            box-shadow: 0 0 15px rgba(255, 107, 0, 0.15);
        }
        .search-icon {
            position: absolute; right: 14px; top: 50%;
            transform: translateY(-50%); width: 18px; height: 18px;
            stroke: var(--text-muted); fill: none; stroke-width: 2;
        }

        .categories { display: flex; gap: 8px; flex-wrap: wrap; }
        .cat-btn {
            background: var(--bg-black); border: 1px solid var(--border-color);
            padding: 7px 14px; border-radius: 30px; font-size: 12px;
            font-weight: 600; color: var(--text-muted); cursor: pointer;
            transition: all 0.3s ease;
        }
        .cat-btn:hover { border-color: var(--brand-green); color: var(--text-main); }
        .cat-btn.active {
            background: var(--brand-orange); color: #fff; border-color: var(--brand-orange);
        }

        /* --- Fluid Grid Cards --- */
        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
        }

        .card {
            background: var(--bg-surface);
            border-radius: var(--radius-lg);
            padding: 26px;
            border: 1px solid var(--border-color);
            box-shadow: 0 4px 16px rgba(0,0,0,0.04);
            transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
            display: flex; flex-direction: column;
            cursor: pointer;
            position: relative;
            overflow: hidden;
            animation: glowPulse 8s infinite;
        }
        .card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--brand-orange), var(--brand-green));
            transform: scaleX(0);
            transform-origin: left;
            transition: transform 0.4s cubic-bezier(0.16, 1, 0.3, 1);
        }
        .card:hover {
            transform: translateY(-8px);
            border-color: var(--brand-green);
            box-shadow: 0 18px 36px rgba(0, 184, 94, 0.12);
        }
        .card:hover::before { transform: scaleX(1); }

        .card-header {
            display: flex; justify-content: space-between;
            align-items: center; margin-bottom: 16px;
        }
        
        .tag-pill {
            color: #fff;
            background: var(--brand-green);
            padding: 5px 10px;
            border-radius: 8px;
            font-weight: 700;
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .date { color: var(--text-muted); font-size: 12px; font-weight: 500; }

        .card h2 {
            margin: 0 0 10px 0; font-size: 17px; line-height: 1.4;
            color: var(--text-main); font-weight: 700; letter-spacing: -0.3px;
        }
        .card p {
            color: var(--text-muted); line-height: 1.6; font-size: 13px;
            margin: 0 0 20px 0; flex-grow: 1;
        }

        .read-more {
            color: var(--brand-orange);
            text-decoration: none; font-weight: 700; font-size: 13px;
            display: inline-flex; align-items: center; gap: 6px;
            transition: 0.3s;
        }
        .card:hover .read-more { color: var(--brand-green); gap: 10px; }

        .status-msg {
            text-align: center; padding: 20px; font-weight: 600; font-size: 13px;
            color: var(--text-muted); display: none; width: 100%;
            border: 1px dashed var(--border-color); border-radius: var(--radius-lg);
            grid-column: 1 / -1;
        }

        .footer {
            text-align: center; padding: 32px 20px; color: var(--text-muted);
            font-size: 12px;
            border-top: 1px solid var(--border-color); margin-top: auto;
        }
        .footer span { color: var(--brand-orange); font-weight: 700; }
    </style>
</head>
<body>

    <!-- Fully Curved Navbar -->
    <nav class="navbar animate-up" style="animation-delay: 0s;">
        <a href="" style="text-decoration:none;">
            <!-- Logo with styled text fallback -->
            <img src="/assets/img/main-png.png" alt="Logo" class="logo-img" 
                 onerror="this.outerHTML='<div class=\'logo-text-fallback\'><span class=\'t1\'>Ship</span><span class=\'t2\'>MyCode</span></div>'">
        </a>
        <button class="menu-btn" id="openMenu" aria-label="Menu">
            <!--<span style="font-weight:600; font-size:13px; margin-right:6px;"></span>-->
            <svg viewBox="0 0 24 24" fill="none">
                <path d="M4 6h16M4 12h16M4 18h16" stroke-linecap="round"/>
            </svg>
        </button>
    </nav>

    <!-- Slide Menu -->
    <div class="slider-menu" id="sliderMenu">
        <button class="close-btn" id="closeMenu">&times;</button>
        <a href="#">Dashboard</a>
        <a href="#">Projects</a>
        <a href="#">Integrations</a>
        <a href="#">Source Code</a>
    </div>

    <!-- Hero Section -->
    <header class="hero animate-up" style="animation-delay: 0.1s;">
        <h1>Explore the <span>Ecosystem</span></h1>
        <p>A highly optimized, serverless architecture directory for your guides, panels, and developer modules.</p>
    </header>

    <main class="container">
        
        <!-- Search & Filter -->
        <div class="tools-card animate-up" style="animation-delay: 0.2s;">
            <div class="search-wrapper">
                <input type="text" id="searchInput" class="search-input" placeholder="Search knowledge base...">
                <svg class="search-icon" viewBox="0 0 24 24">
                    <circle cx="11" cy="11" r="8"></circle>
                    <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                </svg>
            </div>
            
            <div class="categories" id="categoryFilter">
                <button class="cat-btn active" data-cat="all">All Files</button>
                <?php foreach($categories as $cat): ?>
                    <button class="cat-btn" data-cat="<?php echo htmlspecialchars($cat); ?>"><?php echo htmlspecialchars($cat); ?></button>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Content Grid with Fixed Animations -->
        <div class="grid" id="cardGrid">
            <?php 
            $delay = 0.3;
            foreach (array_reverse($filtered_files) as $file): 
                $ext = pathinfo($file, PATHINFO_EXTENSION);
                $name = pathinfo($file, PATHINFO_FILENAME);
                $cat_name = $ext === 'php' ? 'Guides' : ($ext ? strtoupper($ext) : 'Other');
                $display_title = ucwords(str_replace(['-', '_'], ' ', $name));
                $date = date("M j, Y", filemtime(__DIR__ . '/' . $file));
                $description = getFileDescription(__DIR__ . '/' . $file);
            ?>
            <div class="card animate-up" style="animation-delay: <?php echo $delay; ?>s;" 
                 data-title="<?php echo htmlspecialchars(strtolower($display_title)); ?>" 
                 data-desc="<?php echo htmlspecialchars(strtolower($description)); ?>" 
                 data-category="<?php echo htmlspecialchars($cat_name); ?>"
                 onclick="window.location.href='<?php echo htmlspecialchars(urlencode($file)); ?>'">
                <div class="card-header">
                    <span class="tag-pill"><?php echo htmlspecialchars($cat_name); ?></span>
                    <span class="date"><?php echo $date; ?></span>
                </div>
                <h2><?php echo htmlspecialchars($display_title); ?></h2>
                <p><?php echo htmlspecialchars($description); ?></p>
                <div>
                    <span class="read-more">Read article &rarr;</span>
                </div>
            </div>
            <?php 
                $delay += 0.1; // Stagger effect
            endforeach; 
            ?>
            
            <div class="status-msg" id="notFoundMsg">No resources match your search criteria.</div>
            <div class="status-msg" id="onlyOneLeftMsg">Only 1 result remaining.</div>
        </div>
    </main>

    <!-- Footer -->
    <footer class="footer animate-up" style="animation-delay: 1s;">
        &copy; <?php echo date("Y"); ?> <span>ShipMyCode</span>. Engineered for speed.
    </footer>

    <!-- Native JavaScript Engine -->
    <script>
        const menu = document.getElementById('sliderMenu');
        document.getElementById('openMenu').addEventListener('click', () => menu.classList.add('active'));
        document.getElementById('closeMenu').addEventListener('click', () => menu.classList.remove('active'));

        const searchInput = document.getElementById('searchInput');
        const catButtons = document.querySelectorAll('.cat-btn');
        const cards = document.querySelectorAll('.card');
        const notFoundMsg = document.getElementById('notFoundMsg');
        const onlyOneLeftMsg = document.getElementById('onlyOneLeftMsg');
        let currentCategory = 'all';

        function runFilters() {
            const query = searchInput.value.toLowerCase();
            let visibleCount = 0;

            cards.forEach(card => {
                const title = card.getAttribute('data-title');
                const desc = card.getAttribute('data-desc');
                const cat = card.getAttribute('data-category');

                const matchesSearch = title.includes(query) || desc.includes(query);
                const matchesCategory = currentCategory === 'all' || cat === currentCategory;

                if (matchesSearch && matchesCategory) {
                    // Force the card to display completely
                    card.style.display = 'flex';
                    card.style.opacity = '1';
                    card.style.animation = 'none'; // Kill animation to prevent CSS overriding visibility
                    visibleCount++;
                } else {
                    card.style.display = 'none';
                }
            });

            // Status Messages
            if (visibleCount === 0) {
                notFoundMsg.style.display = 'block';
                onlyOneLeftMsg.style.display = 'none';
            } else if (visibleCount === 1) {
                notFoundMsg.style.display = 'none';
                onlyOneLeftMsg.style.display = 'block';
            } else {
                notFoundMsg.style.display = 'none';
                onlyOneLeftMsg.style.display = 'none';
            }
        }

        searchInput.addEventListener('input', runFilters);

        catButtons.forEach(btn => {
            btn.addEventListener('click', (e) => {
                catButtons.forEach(b => b.classList.remove('active'));
                e.target.classList.add('active');
                currentCategory = e.target.getAttribute('data-cat');
                runFilters();
            });
        });
    </script>
</body>
</html>
