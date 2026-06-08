<style>
    /* ── GOOGLE FONT ─────────────────────────────────────────── */
    @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap');

    /* ── CSS VARIABLES ───────────────────────────────────────── */
    :root {
        --primary:     #FF6B35;   /* warm orange */
        --primary-dark:#E85A25;
        --secondary:   #FFB347;   /* golden yellow */
        --accent:      #FF4757;   /* red accent */
        --dark:        #2C1810;   /* deep brown */
        --dark-soft:   #3D2314;
        --sidebar-bg:  #1E0F0A;   /* very dark brown */
        --light-bg:    #FFF8F3;   /* warm white */
        --card-bg:     #FFFFFF;
        --text-main:   #2C1810;
        --text-muted:  #8B6555;
        --border:      #FFE4D0;
        --success:     #2ECC71;
        --warning:     #F39C12;
        --danger:      #E74C3C;
        --info:        #3498DB;
    }

    /* ── BASE ────────────────────────────────────────────────── */
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body {
        background: var(--light-bg);
        font-family: 'Poppins', 'Segoe UI', sans-serif;
        color: var(--text-main);
    }

    /* ── SIDEBAR ─────────────────────────────────────────────── */
    .sidebar {
        width: 255px;
        min-height: 100vh;
        background: var(--sidebar-bg);
        position: fixed;
        top: 0; left: 0;
        padding: 0;
        z-index: 100;
        display: flex;
        flex-direction: column;
        box-shadow: 4px 0 20px rgba(255,107,53,0.15);
    }

    /* Sidebar brand area */
    .sidebar-brand {
        padding: 1.5rem 1.2rem 1rem;
        background: linear-gradient(135deg, var(--primary), var(--primary-dark));
        position: relative;
        overflow: hidden;
    }
    .sidebar-brand::before {
        content: '🍽️';
        position: absolute;
        right: -10px; top: -10px;
        font-size: 5rem;
        opacity: 0.1;
    }
    .sidebar-brand h4 {
        color: white;
        font-weight: 800;
        font-size: 1.2rem;
        margin: 0;
        letter-spacing: 0.5px;
    }
    .sidebar-brand .tagline {
        color: rgba(255,255,255,0.75);
        font-size: 0.72rem;
        margin-top: 0.2rem;
        font-weight: 400;
    }

    /* Sidebar nav */
    .sidebar-nav {
        flex: 1;
        padding: 1rem 0.8rem;
        overflow-y: auto;
    }
    .sidebar .section-label {
        color: var(--primary);
        font-size: 0.65rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 1.5px;
        padding: 0.8rem 0.5rem 0.3rem;
        margin-top: 0.3rem;
        opacity: 0.8;
    }
    .sidebar a {
        display: flex;
        align-items: center;
        gap: 0.6rem;
        color: rgba(255,255,255,0.6);
        padding: 0.6rem 0.8rem;
        border-radius: 10px;
        text-decoration: none;
        font-size: 0.875rem;
        font-weight: 500;
        margin-bottom: 0.2rem;
        transition: all 0.2s ease;
        position: relative;
    }
    .sidebar a:hover {
        background: rgba(255,107,53,0.15);
        color: var(--secondary);
        transform: translateX(3px);
    }
    .sidebar a.active {
        background: linear-gradient(135deg, var(--primary), var(--primary-dark));
        color: white;
        box-shadow: 0 4px 12px rgba(255,107,53,0.4);
        transform: translateX(0);
    }

    /* Sidebar user info */
    .sidebar .user-info {
        margin: 0.8rem;
        background: rgba(255,107,53,0.15);
        border: 1px solid rgba(255,107,53,0.2);
        border-radius: 12px;
        padding: 0.8rem 1rem;
    }
    .sidebar .user-info .name {
        color: white;
        font-weight: 600;
        font-size: 0.875rem;
    }
    .sidebar .user-info .role {
        font-size: 0.7rem;
        padding: 0.15rem 0.6rem;
        border-radius: 20px;
        display: inline-block;
        margin-top: 0.3rem;
        font-weight: 600;
    }
    .role-admin   { background: var(--secondary); color: var(--dark); }
    .role-cashier { background: rgba(255,255,255,0.15); color: white; }

    /* ── MAIN CONTENT ────────────────────────────────────────── */
    .main {
        margin-left: 255px;
        padding: 2rem;
        min-height: 100vh;
    }

    /* ── TOP BAR ─────────────────────────────────────────────── */
    .topbar {
        background: white;
        border-radius: 16px;
        padding: 1rem 1.5rem;
        margin-bottom: 1.5rem;
        box-shadow: 0 2px 12px rgba(255,107,53,0.08);
        display: flex;
        justify-content: space-between;
        align-items: center;
        border: 1px solid var(--border);
    }
    .topbar h4 {
        font-weight: 700;
        color: var(--dark);
        margin: 0;
        font-size: 1.3rem;
    }
    .topbar small { color: var(--text-muted); font-size: 0.78rem; }

    /* ── PAGE CARDS ──────────────────────────────────────────── */
    .page-card {
        background: white;
        border-radius: 16px;
        padding: 1.5rem;
        box-shadow: 0 2px 12px rgba(255,107,53,0.06);
        margin-bottom: 1.5rem;
        border: 1px solid var(--border);
    }
    .page-card h6 {
        font-weight: 700;
        color: var(--dark);
        margin-bottom: 1rem;
        font-size: 0.95rem;
    }

    /* ── SUMMARY CARDS ───────────────────────────────────────── */
    .summary-card {
        border-radius: 16px;
        padding: 1.5rem;
        color: white;
        margin-bottom: 1rem;
        position: relative;
        overflow: hidden;
    }
    .summary-card::after {
        content: '';
        position: absolute;
        right: -20px; bottom: -20px;
        width: 100px; height: 100px;
        border-radius: 50%;
        background: rgba(255,255,255,0.1);
    }
    .summary-card .value {
        font-size: 2rem;
        font-weight: 800;
        line-height: 1.1;
    }
    .summary-card .label {
        font-size: 0.8rem;
        opacity: 0.85;
        font-weight: 500;
        margin-bottom: 0.3rem;
    }
    .summary-card .icon {
        font-size: 2rem;
        position: absolute;
        right: 1.2rem;
        top: 1.2rem;
        opacity: 0.3;
    }
    .card-orange { background: linear-gradient(135deg, #FF6B35, #E85A25); }
    .card-yellow { background: linear-gradient(135deg, #FFB347, #FF8C00); }
    .card-red    { background: linear-gradient(135deg, #FF4757, #C0392B); }
    .card-green  { background: linear-gradient(135deg, #2ECC71, #1E8449); }
    .card-blue   { background: linear-gradient(135deg, #3498DB, #1A5276); }
    .card-purple { background: linear-gradient(135deg, #8E44AD, #6C3483); }
    .card-brown  { background: linear-gradient(135deg, #795548, #4E342E); }

    /* ── TABLES ──────────────────────────────────────────────── */
    .table { border-radius: 12px; overflow: hidden; }
    .table thead th {
        background: linear-gradient(135deg, var(--dark), var(--dark-soft));
        color: white;
        font-size: 0.8rem;
        font-weight: 600;
        letter-spacing: 0.3px;
        padding: 0.9rem 1rem;
        border: none;
    }
    .table tbody td {
        padding: 0.8rem 1rem;
        vertical-align: middle;
        border-color: var(--border);
        font-size: 0.875rem;
    }
    .table tbody tr:hover { background: #FFF5EE; }

    /* ── BUTTONS ─────────────────────────────────────────────── */
    .btn-primary-warm {
        background: linear-gradient(135deg, var(--primary), var(--primary-dark));
        color: white;
        border: none;
        border-radius: 10px;
        padding: 0.5rem 1.2rem;
        font-weight: 600;
        font-size: 0.875rem;
        transition: all 0.2s;
        box-shadow: 0 4px 12px rgba(255,107,53,0.3);
    }
    .btn-primary-warm:hover {
        transform: translateY(-1px);
        box-shadow: 0 6px 16px rgba(255,107,53,0.4);
        color: white;
    }
    .btn-danger {
        background: linear-gradient(135deg, var(--primary), var(--primary-dark)) !important;
        border: none !important;
        border-radius: 10px !important;
        font-weight: 600 !important;
        box-shadow: 0 4px 12px rgba(255,107,53,0.3) !important;
    }
    .btn-danger:hover {
        transform: translateY(-1px) !important;
        box-shadow: 0 6px 16px rgba(255,107,53,0.4) !important;
    }
    .btn-outline-danger {
        color: var(--primary) !important;
        border-color: var(--primary) !important;
        border-radius: 10px !important;
        font-weight: 600 !important;
    }
    .btn-outline-danger:hover {
        background: var(--primary) !important;
        color: white !important;
    }
    .btn-outline-secondary {
        border-radius: 10px !important;
        font-weight: 500 !important;
    }
    .btn-outline-primary {
        color: var(--info) !important;
        border-color: var(--info) !important;
        border-radius: 10px !important;
    }
    .btn-outline-success {
        color: var(--success) !important;
        border-color: var(--success) !important;
        border-radius: 10px !important;
    }
    .btn-outline-warning {
        color: var(--warning) !important;
        border-color: var(--warning) !important;
        border-radius: 10px !important;
    }

    /* ── FORM CONTROLS ───────────────────────────────────────── */
    .form-control, .form-select {
        border: 1.5px solid var(--border) !important;
        border-radius: 10px !important;
        padding: 0.6rem 0.9rem !important;
        font-size: 0.875rem !important;
        font-family: 'Poppins', sans-serif !important;
        transition: all 0.2s !important;
    }
    .form-control:focus, .form-select:focus {
        border-color: var(--primary) !important;
        box-shadow: 0 0 0 3px rgba(255,107,53,0.12) !important;
    }
    .form-label {
        font-size: 0.82rem !important;
        font-weight: 600 !important;
        color: var(--dark) !important;
        margin-bottom: 0.3rem !important;
    }

    /* ── MODALS ──────────────────────────────────────────────── */
    .modal-content {
        border-radius: 20px !important;
        border: none !important;
        box-shadow: 0 20px 60px rgba(0,0,0,0.15) !important;
        overflow: hidden;
    }
    .modal-header {
        background: linear-gradient(135deg, var(--dark), var(--dark-soft));
        color: white;
        border: none !important;
        padding: 1.2rem 1.5rem !important;
    }
    .modal-title { font-weight: 700 !important; font-size: 1rem !important; }
    .btn-close-white { filter: invert(1) !important; }
    .modal-footer { border: none !important; padding: 1rem 1.5rem !important; }

    /* ── ALERTS ──────────────────────────────────────────────── */
    .alert {
        border-radius: 12px !important;
        border: none !important;
        font-size: 0.875rem !important;
        font-weight: 500 !important;
    }
    .alert-success { background: #D5F5E3 !important; color: #1E8449 !important; }
    .alert-danger  { background: #FADBD8 !important; color: #C0392B !important; }
    .alert-warning { background: #FEF9E7 !important; color: #D68910 !important; }
    .alert-info    { background: #D6EAF8 !important; color: #1A5276 !important; }

    /* ── BADGES ──────────────────────────────────────────────── */
    .badge-completed {
        background: #D5F5E3; color: #1E8449;
        border-radius: 20px; padding: 0.25rem 0.8rem;
        font-size: 0.75rem; font-weight: 600;
    }
    .badge-pending {
        background: #FEF9E7; color: #D68910;
        border-radius: 20px; padding: 0.25rem 0.8rem;
        font-size: 0.75rem; font-weight: 600;
    }
    .badge-cancelled {
        background: #FADBD8; color: #C0392B;
        border-radius: 20px; padding: 0.25rem 0.8rem;
        font-size: 0.75rem; font-weight: 600;
    }

    /* ── SCROLLBAR ───────────────────────────────────────────── */
    ::-webkit-scrollbar { width: 6px; }
    ::-webkit-scrollbar-track { background: var(--light-bg); }
    ::-webkit-scrollbar-thumb {
        background: var(--primary);
        border-radius: 3px;
    }

    /* ── PROGRESS BARS ───────────────────────────────────────── */
    .progress { border-radius: 10px !important; }
    .progress-bar { background: var(--primary) !important; }

    /* ── FILTER PILLS ────────────────────────────────────────── */
    .filter-pill {
        display: inline-block;
        padding: 0.35rem 1rem;
        border-radius: 20px;
        font-size: 0.8rem;
        font-weight: 500;
        text-decoration: none;
        border: 1.5px solid var(--border);
        color: var(--text-muted);
        margin-right: 0.4rem;
        transition: all 0.2s;
    }
    .filter-pill:hover,
    .filter-pill.active {
        background: var(--primary);
        color: white;
        border-color: var(--primary);
        box-shadow: 0 4px 12px rgba(255,107,53,0.3);
    }

    /* ── SECTION DIVIDER ─────────────────────────────────────── */
    .section-title {
        font-size: 0.7rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 1.5px;
        color: var(--text-muted);
        margin-bottom: 0.8rem;
        padding-bottom: 0.5rem;
        border-bottom: 2px solid var(--border);
    }

    /* ── FOOD DECORATIVE ELEMENTS ────────────────────────────── */
    .food-header {
        background: linear-gradient(135deg, var(--primary), var(--secondary));
        border-radius: 16px;
        padding: 1.5rem;
        color: white;
        margin-bottom: 1.5rem;
        position: relative;
        overflow: hidden;
    }
    .food-header::before {
        content: '🍽️';
        position: absolute;
        right: 1rem; top: 50%;
        transform: translateY(-50%);
        font-size: 4rem;
        opacity: 0.2;
    }
    .food-header h4 { font-weight: 800; margin: 0; font-size: 1.4rem; }
    .food-header small { opacity: 0.85; font-size: 0.8rem; }

    /* ── RESPONSIVE TWEAKS ───────────────────────────────────── */
    @media (max-width: 768px) {
        .sidebar { width: 100%; min-height: auto; position: relative; }
        .main { margin-left: 0; padding: 1rem; }
    }
</style>