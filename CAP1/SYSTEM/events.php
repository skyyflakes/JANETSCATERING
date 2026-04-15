<?php
/**
 * Events Page - Janet's Quality Catering System
 * Features: Full CRUD operations with PH Address (PSGC) and Inventory Deduction
 */
$page_title = "Events | Janet's Quality Catering";
$current_page = 'events';

require_once 'includes/auth_check.php';

$pdo = getDBConnection();
$action = $_GET['action'] ?? '';
$event_id = $_GET['id'] ?? null;

// Function to deduct inventory based on pax
function deductInventoryForEvent($pdo, $pax, $event_id = null) {
    // Define usage rates per pax for each category
    $usage_rates = [
        'Silverware' => ['rate' => 1, 'items' => ['Spoon', 'Fork', 'Teaspoon', 'Dinner Knife']],
        'Dinnerware' => ['rate' => 1, 'items' => ['Dinner Plate', 'Soup Bowl', 'Salad Plate', 'Dessert Plate']],
        'Glassware' => ['rate' => 2, 'items' => ['Wine Glass', 'Water Goblet', 'Juice Glass']],
        'Linens' => ['rate' => 0.1, 'items' => ['Table Cloth (White)', 'Table Napkin (White)']]
    ];
    
    $deductions = [];
    
    foreach ($usage_rates as $category => $config) {
        $stmt = $pdo->prepare("
            SELECT i.item_id, i.item_name, i.ending_qty, i.extra_qty, i.previous_qty, c.category_name
            FROM inventory i
            LEFT JOIN categories c ON i.category_id = c.category_id
            WHERE c.category_name = ? AND i.is_active = 1
        ");
        $stmt->execute([$category]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($items as $item) {
            $deduction = ceil($pax * $config['rate']);
            $new_qty = max(0, $item['ending_qty'] - $deduction);
            
            $updateStmt = $pdo->prepare("UPDATE inventory SET ending_qty = ?, updated_at = NOW() WHERE item_id = ?");
            $updateStmt->execute([$new_qty, $item['item_id']]);
            
            $deductions[] = [
                'item' => $item['item_name'],
                'deducted' => $deduction,
                'remaining' => $new_qty
            ];
        }
    }
    
    return $deductions;
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form_action = $_POST['form_action'] ?? '';
    
    if ($pdo) {
        try {
            switch ($form_action) {
                case 'add_event':
                    $pax = (int)$_POST['pax'];
                    
                    $stmt = $pdo->prepare("INSERT INTO events (event_name, event_date, fullname, email, contact, customer_address, province, city, barangay, venue_address, pax, backdrop, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([
                        sanitize($_POST['event_name']),
                        $_POST['event_date'],
                        sanitize($_POST['fullname']),
                        sanitize($_POST['email']),
                        sanitize($_POST['contact']),
                        sanitize($_POST['customer_address']),
                        sanitize($_POST['province']),
                        sanitize($_POST['city']),
                        sanitize($_POST['barangay']),
                        sanitize($_POST['venue_address']),
                        $pax,
                        sanitize($_POST['backdrop'] ?? ''),
                        'Pending'
                    ]);
                    
                    $new_event_id = $pdo->lastInsertId();
                    
                    // Deduct inventory based on pax
                    $deductions = deductInventoryForEvent($pdo, $pax, $new_event_id);
                    
                    unset($_SESSION['event_form_data']);
                    unset($_SESSION['selected_backdrop']);
                    setFlash('Event booked successfully! Inventory has been deducted for ' . $pax . ' guests.', 'success');
                    break;

                case 'edit_event':
                    $stmt = $pdo->prepare("UPDATE events SET event_name = ?, event_date = ?, fullname = ?, email = ?, contact = ?, customer_address = ?, province = ?, city = ?, barangay = ?, venue_address = ?, pax = ?, backdrop = ?, status = ? WHERE id = ?");
                    $stmt->execute([
                        sanitize($_POST['event_name']),
                        $_POST['event_date'],
                        sanitize($_POST['fullname']),
                        sanitize($_POST['email']),
                        sanitize($_POST['contact']),
                        sanitize($_POST['customer_address']),
                        sanitize($_POST['province']),
                        sanitize($_POST['city']),
                        sanitize($_POST['barangay']),
                        sanitize($_POST['venue_address']),
                        (int)$_POST['pax'],
                        sanitize($_POST['backdrop'] ?? ''),
                        $_POST['status'],
                        $_POST['event_id']
                    ]);
                    setFlash('Event updated successfully!', 'success');
                    break;

                case 'delete_event':
                    $stmt = $pdo->prepare("DELETE FROM events WHERE id = ?");
                    $stmt->execute([$_POST['event_id']]);
                    setFlash('Event deleted successfully!', 'success');
                    break;

                case 'update_status':
                    $stmt = $pdo->prepare("UPDATE events SET status = ? WHERE id = ?");
                    $stmt->execute([$_POST['status'], $_POST['event_id']]);
                    setFlash('Event status updated!', 'success');
                    break;
                
                case 'save_form_data':
                    $_SESSION['event_form_data'] = [
                        'fullname' => sanitize($_POST['fullname'] ?? ''),
                        'email' => sanitize($_POST['email'] ?? ''),
                        'contact' => sanitize($_POST['contact'] ?? ''),
                        'customer_address' => sanitize($_POST['customer_address'] ?? ''),
                        'event_name' => sanitize($_POST['event_name'] ?? ''),
                        'event_date' => $_POST['event_date'] ?? '',
                        'pax' => (int)($_POST['pax'] ?? 50),
                        'province' => sanitize($_POST['province'] ?? ''),
                        'city' => sanitize($_POST['city'] ?? ''),
                        'barangay' => sanitize($_POST['barangay'] ?? ''),
                        'venue_address' => sanitize($_POST['venue_address'] ?? '')
                    ];
                    redirect('backdrops.php');
                    exit;
            }
        } catch (PDOException $e) {
            setFlash('Error: ' . $e->getMessage(), 'danger');
        }
    }
    
    if ($form_action !== 'save_form_data') {
        redirect('events.php');
    }
}

// Fetch events
$events = [];
$edit_event = null;
$selected_backdrop = $_SESSION['selected_backdrop'] ?? '';
$form_data = $_SESSION['event_form_data'] ?? [];

if ($pdo) {
    $stmt = $pdo->query("SELECT * FROM events ORDER BY event_date DESC");
    $events = $stmt->fetchAll();

    if ($action === 'edit' && $event_id) {
        $stmt = $pdo->prepare("SELECT * FROM events WHERE id = ?");
        $stmt->execute([$event_id]);
        $edit_event = $stmt->fetch();
    }
}

// Event types
$event_types = [
    'Wedding Reception',
    'Birthday Party',
    'Debut (18th Birthday)',
    'Christening / Baptism',
    'Anniversary',
    'Corporate Event',
    'Graduation Party',
    'Fiesta / Town Celebration',
    'Reunion',
    'Seminar / Conference',
    'Other'
];

// Complete Philippine Address Data (PSGC-based structure)
$ph_address_data = [
    'Metro Manila' => [
        'Caloocan City' => ['Bagong Barrio', 'Camarin', 'Deparo', 'Grace Park East', 'Grace Park West', 'Llano', 'Maypajo', 'Novaliches Proper', 'Tala', 'Zabarte', 'Amparo', 'Bagumbong', 'Banaba', 'Barangay 1', 'Barangay 2', 'Barangay 3'],
        'Las Pinas City' => ['Almanza Uno', 'Almanza Dos', 'BF International', 'BF Homes', 'CAA', 'Daniel Fajardo', 'Elias Aldana', 'Ilaya', 'Manuyo Uno', 'Manuyo Dos', 'Pamplona Uno', 'Pamplona Dos', 'Pamplona Tres', 'Pilar', 'Pulang Lupa Uno', 'Pulang Lupa Dos', 'Talon Uno', 'Talon Dos', 'Talon Tres', 'Talon Kuatro', 'Talon Singko', 'Zapote'],
        'Makati City' => ['Bangkal', 'Bel-Air', 'Carmona', 'Cembo', 'Comembo', 'Dasmarinas', 'East Rembo', 'Forbes Park', 'Guadalupe Nuevo', 'Guadalupe Viejo', 'Kasilawan', 'La Paz', 'Magallanes', 'Olympia', 'Palanan', 'Pembo', 'Pinagkaisahan', 'Pio del Pilar', 'Pitogo', 'Poblacion', 'Rizal', 'San Antonio', 'San Isidro', 'San Lorenzo', 'Santa Cruz', 'Singkamas', 'South Cembo', 'Tejeros', 'Urdaneta', 'Valenzuela', 'West Rembo', 'Legaspi Village', 'Salcedo Village'],
        'Manila City' => ['Binondo', 'Ermita', 'Intramuros', 'Malate', 'Paco', 'Pandacan', 'Port Area', 'Quiapo', 'Sampaloc', 'San Andres', 'San Miguel', 'San Nicolas', 'Santa Ana', 'Santa Cruz', 'Santa Mesa', 'Tondo'],
        'Mandaluyong City' => ['Addition Hills', 'Bagong Silang', 'Barangka Drive', 'Barangka Ibaba', 'Barangka Ilaya', 'Barangka Itaas', 'Buayang Bato', 'Burol', 'Hagdang Bato Itaas', 'Hagdang Bato Libis', 'Highway Hills', 'Hulo', 'Mabini-J. Rizal', 'Malamig', 'Mauway', 'Namayan', 'Pag-asa', 'Plainview', 'Pleasant Hills', 'Poblacion', 'San Jose', 'Vergara', 'Wack-Wack Greenhills'],
        'Marikina City' => ['Barangka', 'Calumpang', 'Concepcion Uno', 'Concepcion Dos', 'Fortune', 'Industrial Valley', 'Jesus de la Pena', 'Malanday', 'Marikina Heights', 'Nangka', 'Parang', 'San Roque', 'Santa Elena', 'Santo Nino', 'Tanong', 'Tumana'],
        'Muntinlupa City' => ['Alabang', 'Ayala Alabang', 'Bayanan', 'Buli', 'Cupang', 'New Alabang Village', 'Poblacion', 'Putatan', 'Sucat', 'Tunasan'],
        'Paranaque City' => ['Baclaran', 'BF Homes', 'Don Bosco', 'Don Galo', 'La Huerta', 'Marcelo Green', 'Merville', 'Moonwalk', 'San Antonio', 'San Dionisio', 'San Isidro', 'San Martin de Porres', 'Santo Nino', 'Sun Valley', 'Tambo', 'Vitalez', 'Better Living'],
        'Pasay City' => ['Barangay 1-20', 'Barangay 21-40', 'Barangay 41-60', 'Barangay 61-80', 'Barangay 81-100', 'Barangay 101-120', 'Barangay 121-140', 'Barangay 141-160', 'Barangay 161-180', 'Barangay 181-200'],
        'Pasig City' => ['Bagong Ilog', 'Bagong Katipunan', 'Bambang', 'Buting', 'Caniogan', 'Dela Paz', 'Kalawaan', 'Kapitolyo', 'Malinao', 'Manggahan', 'Maybunga', 'Oranbo', 'Palatiw', 'Pinagbuhatan', 'Pineda', 'Rosario', 'Sagad', 'San Antonio', 'San Joaquin', 'San Jose', 'San Miguel', 'San Nicolas', 'Santa Cruz', 'Santa Lucia', 'Santa Rosa', 'Santolan', 'Santo Tomas', 'Sumilang', 'Ugong'],
        'Quezon City' => ['Alicia', 'Bagong Pag-asa', 'Bagong Silangan', 'Bagumbayan', 'Bahay Toro', 'Balingasa', 'Batasan Hills', 'Botocan', 'Central', 'Commonwealth', 'Culiat', 'Damar', 'Damayang Lagi', 'Diliman', 'Don Manuel', 'Dona Josefa', 'Fairview', 'Greater Lagro', 'Holy Spirit', 'Kaligayahan', 'Kamuning', 'Katipunan', 'Krus na Ligas', 'Laging Handa', 'Loyola Heights', 'Maharlika', 'Malaya', 'Masagana', 'Matandang Balara', 'Novaliches Proper', 'Pag-ibig sa Nayon', 'Paltok', 'Pansol', 'Payatas', 'Phil-Am', 'Pinagkaisahan', 'Project 4', 'Project 6', 'Project 7', 'Project 8', 'Quezon Memorial Circle', 'Quirino 2-A', 'Ramon Magsaysay', 'Roxas', 'San Agustin', 'San Bartolome', 'San Isidro', 'San Roque', 'Santa Cruz', 'Santa Lucia', 'Santa Monica', 'Santo Cristo', 'Santo Nino', 'Tandang Sora', 'Teachers Village', 'UP Campus', 'UP Village', 'Valencia', 'Varsity Hills'],
        'San Juan City' => ['Addition Hills', 'Balong-Bato', 'Batis', 'Corazon de Jesus', 'Ermitano', 'Greenhills', 'Isabelita', 'Kabayanan', 'Little Baguio', 'Maytunas', 'Onse', 'Pasadena', 'Pedro Cruz', 'Progreso', 'Rivera', 'Salapan', 'San Perfecto', 'Santa Lucia', 'Tibagan', 'West Crame'],
        'Taguig City' => ['Bagumbayan', 'Bambang', 'Calzada', 'Central Bicutan', 'Fort Bonifacio', 'Hagonoy', 'Ibayo-Tipas', 'Ligid-Tipas', 'Lower Bicutan', 'New Lower Bicutan', 'Pinagsama', 'San Miguel', 'Santa Ana', 'Signal Village', 'Tuktukan', 'Upper Bicutan', 'Ususan', 'Western Bicutan', 'BGC', 'McKinley Hill'],
        'Valenzuela City' => ['Arkong Bato', 'Bagbaguin', 'Balangkas', 'Bignay', 'Bisig', 'Canumay East', 'Canumay West', 'Coloong', 'Dalandanan', 'Gen. T. de Leon', 'Isla', 'Karuhatan', 'Lawang Bato', 'Lingunan', 'Mabolo', 'Malanday', 'Malinta', 'Mapulang Lupa', 'Marulas', 'Maysan', 'Palasan', 'Parada', 'Paso de Blas', 'Pasolo', 'Poblacion', 'Polo', 'Punturin', 'Rincon', 'Tagalag', 'Ugong', 'Veinte Reales']
    ],
    'Bulacan' => [
        'Angat' => ['Banaban', 'Baybay', 'Binagbag', 'Donacion', 'Encanto', 'Laog', 'Marungko', 'Niugan', 'Paltok', 'Pulong Yantok', 'San Roque', 'Santa Cruz', 'Santa Lucia', 'Santo Cristo', 'Taboc'],
        'Balagtas' => ['Borol 1st', 'Borol 2nd', 'Dalig', 'Longos', 'Panginay', 'Pulong Gubat', 'San Juan', 'Santol', 'Wawa'],
        'Baliuag' => ['Bagong Nayon', 'Barangca', 'Calantipay', 'Catulinan', 'Concepcion', 'Hinukay', 'Makinabang', 'Matangtubig', 'Pagala', 'Paitan', 'Piel', 'Pinagbarilan', 'Poblacion', 'Sabang', 'San Jose', 'San Roque', 'Santa Barbara', 'Santo Cristo', 'Santo Nino', 'Subic', 'Sulivan', 'Tangos', 'Tarcan', 'Tiaong', 'Tibag'],
        'Bocaue' => ['Antipona', 'Bagumbayan', 'Bambang', 'Batia', 'Binang 1st', 'Binang 2nd', 'Bolacan', 'Bundukan', 'Bunlo', 'Caingin', 'Duhat', 'Igulot', 'Lolomboy', 'Poblacion', 'Sulucan', 'Taal', 'Tambobong', 'Turo', 'Wakas'],
        'Bulakan' => ['Bagumbayan', 'Balubad', 'Bambang', 'Matungao', 'Maysantol', 'Perez', 'Pitpitan', 'Poblacion', 'San Francisco', 'San Jose', 'San Nicolas', 'Santa Ana', 'Santa Ines', 'Taliptip', 'Tibig'],
        'Bustos' => ['Bonga Mayor', 'Bonga Menor', 'Buisan', 'Camachilihan', 'Cambaog', 'Catacutan', 'Liciada', 'Malamig', 'Malawak', 'Poblacion', 'San Pedro', 'Talampas', 'Tanawan', 'Tibagan'],
        'Calumpit' => ['Balite', 'Balungao', 'Buguion', 'Bulusan', 'Calizon', 'Calumpang', 'Caniogan', 'Corazon', 'Frances', 'Gatbuca', 'Gugo', 'Iba Este', 'Iba Ibaba', 'Longos', 'Meysulao', 'Meyto', 'Palimbang', 'Panducot', 'Piel', 'Poblacion', 'Pio Cruzcosa', 'San Jose', 'San Marcos', 'San Miguel', 'Santa Lucia', 'Sapang Bayan'],
        'Guiguinto' => ['Daungan', 'Ilang-Ilang', 'Malis', 'Panginay', 'Poblacion', 'Pritil', 'Pulong Gubat', 'Santa Cruz', 'Santa Rita', 'Tabang', 'Tabe', 'Tiaong', 'Tuktukan'],
        'Hagonoy' => ['Abulalas', 'Carillo', 'Iba', 'Iba Este', 'Mercado', 'Palapat', 'Poblacion', 'Pugad', 'Sagrada Familia', 'San Agustin', 'San Isidro', 'San Jose', 'San Juan', 'San Miguel', 'San Nicolas', 'San Pablo', 'San Pascual', 'San Pedro', 'San Roque', 'San Sebastian', 'Santa Cruz', 'Santa Elena', 'Santa Monica', 'Santo Nino', 'Santo Rosario', 'Tampok', 'Tibaguin'],
        'Malolos City' => ['Anilao', 'Atlag', 'Babatnin', 'Bagna', 'Bagong Bayan', 'Balayong', 'Balite', 'Bangkal', 'Barihan', 'Bulihan', 'Bungahan', 'Caingin', 'Calero', 'Caliligawan', 'Canalate', 'Caniogan', 'Catmon', 'Cofradia', 'Dakila', 'Guinhawa', 'Ligas', 'Liyang', 'Longos', 'Look 1st', 'Look 2nd', 'Lugam', 'Mabolo', 'Mambog', 'Masile', 'Matimbo', 'Mojon', 'Namayan', 'Niugan', 'Pamarawan', 'Panasahan', 'Pinagbakahan', 'San Agustin', 'San Gabriel', 'San Juan', 'San Pablo', 'San Vicente', 'Santiago', 'Santisima Trinidad', 'Santo Cristo', 'Santo Nino', 'Santo Rosario', 'Santol', 'Sumapang Bata', 'Sumapang Matanda', 'Taal', 'Tikay'],
        'Meycauayan City' => ['Bagbaguin', 'Bahay Pare', 'Bancal', 'Banga', 'Bayugo', 'Caingin', 'Calvario', 'Camalig', 'Hulo', 'Iba', 'Langka', 'Lawa', 'Libtong', 'Liputan', 'Longos', 'Malhacan', 'Pajo', 'Pandayan', 'Pantoc', 'Perez', 'Poblacion', 'Saluysoy', 'Saint Francis', 'Tugatog', 'Ubihan', 'Zamora'],
        'Obando' => ['Binuangan', 'Catanghalan', 'Hulo', 'Lawa', 'Malis', 'Paco', 'Paliwas', 'Panghulo', 'Poblacion', 'Salambao', 'San Pascual', 'Tawiran'],
        'Pandi' => ['Bagong Barrio', 'Bunsuran I', 'Bunsuran II', 'Bunsuran III', 'Cacarong Bata', 'Cacarong Matanda', 'Cupang', 'Malibo Bata', 'Malibo Matanda', 'Manatal', 'Mapulang Lupa', 'Masagana', 'Masuso', 'Pinagkuartelan', 'Poblacion', 'Real de Cacarong', 'San Roque', 'Siling Bata', 'Siling Matanda'],
        'Plaridel' => ['Agnaya', 'Bagong Silang', 'Banga I', 'Banga II', 'Bintog', 'Bulihan', 'Culianin', 'Dampol I', 'Dampol II-A', 'Dampol II-B', 'Dulong Malabon', 'Lagundi', 'Maasim', 'Maysantol', 'Pabahay 2000', 'Parulan', 'Poblacion', 'Rueda', 'San Jose', 'Santa Ines', 'Santo Nino', 'Sipat', 'Tabang'],
        'Pulilan' => ['Balatong A', 'Balatong B', 'Cutcot', 'Dampol', 'Dulong Bayan', 'Inaon', 'Longos', 'Lumbac', 'Paltao', 'Penabatan', 'Poblacion', 'Santa Peregrina', 'Santo Cristo', 'Taal', 'Tabon', 'Tibag', 'Tinejero'],
        'San Jose del Monte City' => ['Assumption', 'Bagong Buhay', 'Bagong Buhay II', 'Bagong Buhay III', 'Citrus', 'Ciudad Real', 'Dulong Bayan', 'Fatima', 'Fatima II', 'Fatima III', 'Francisco Homes-Mulawin', 'Francisco Homes-Narra', 'Francisco Homes-Yakal', 'Gaya-gaya', 'Graceville', 'Gumaoc Central', 'Gumaoc East', 'Gumaoc West', 'Kaybanban', 'Kaypian', 'Lawang Pari', 'Maharlika', 'Minuyan', 'Minuyan II', 'Minuyan III', 'Minuyan IV', 'Minuyan Proper', 'Muzon', 'Poblacion', 'Poblacion I', 'San Isidro', 'San Manuel', 'San Martin', 'San Martin II', 'San Martin III', 'San Martin IV', 'San Pedro', 'San Rafael', 'San Rafael I', 'San Rafael II', 'San Rafael III', 'San Rafael IV', 'San Rafael V', 'San Roque', 'Santa Cruz', 'Santa Cruz II', 'Santa Cruz III', 'Santo Cristo', 'Santo Nino', 'Santo Nino II', 'Sapang Palay', 'Sapang Palay Proper', 'Tungkong Mangga'],
        'Santa Maria' => ['Bagbaguin', 'Balasing', 'Buenavista', 'Bulac', 'Camangyanan', 'Catmon', 'Cay Pombo', 'Caysio', 'Guyong', 'Lalakhan', 'Mag-asawang Sapa', 'Mahabang Parang', 'Manggahan', 'Parada', 'Poblacion', 'Pulong Buhangin', 'San Gabriel', 'San Jose Patag', 'San Vicente', 'Santa Clara', 'Santa Cruz', 'Silangan', 'Tabing Bakod', 'Tumana']
    ],
    'Cavite' => [
        'Bacoor City' => ['Alima', 'Aniban I', 'Aniban II', 'Aniban III', 'Aniban IV', 'Aniban V', 'Habay I', 'Habay II', 'Ligas I', 'Ligas II', 'Ligas III', 'Mabolo I', 'Mabolo II', 'Mabolo III', 'Maliksi I', 'Maliksi II', 'Maliksi III', 'Molino I', 'Molino II', 'Molino III', 'Molino IV', 'Molino V', 'Molino VI', 'Molino VII', 'Niog I', 'Niog II', 'Niog III', 'Panapaan I', 'Panapaan II', 'Panapaan III', 'Panapaan IV', 'Panapaan V', 'Panapaan VI', 'Panapaan VII', 'Panapaan VIII', 'Queens Row Central', 'Queens Row East', 'Queens Row West', 'Real I', 'Real II', 'Salinas I', 'Salinas II', 'Salinas III', 'Salinas IV', 'San Nicolas I', 'San Nicolas II', 'San Nicolas III', 'Sineguelasan', 'Tabing Dagat', 'Talaba I', 'Talaba II', 'Talaba III', 'Talaba IV', 'Talaba V', 'Talaba VI', 'Talaba VII', 'Zapote I', 'Zapote II', 'Zapote III', 'Zapote IV', 'Zapote V'],
        'Cavite City' => ['Barangay 1', 'Barangay 2', 'Barangay 3', 'Barangay 4', 'Barangay 5', 'Barangay 6', 'Barangay 7', 'Barangay 8', 'Barangay 9', 'Barangay 10', 'Caridad', 'Dalahican', 'San Antonio', 'Santa Cruz'],
        'Dasmarinas City' => ['Burol I', 'Burol II', 'Burol III', 'Datu Esmael', 'Emmanuel Bergado I', 'Emmanuel Bergado II', 'Fatima I', 'Fatima II', 'Fatima III', 'H-2', 'Kaybagal Central', 'Kaybagal North', 'Kaybagal South', 'Langkaan I', 'Langkaan II', 'Luzviminda I', 'Luzviminda II', 'Paliparan I', 'Paliparan II', 'Paliparan III', 'Sabang', 'Salawag', 'Salitran I', 'Salitran II', 'Salitran III', 'Salitran IV', 'Sampaloc I', 'Sampaloc II', 'Sampaloc III', 'Sampaloc IV', 'Sampaloc V', 'San Agustin I', 'San Agustin II', 'San Agustin III', 'San Andres I', 'San Andres II', 'San Isidro Labrador I', 'San Isidro Labrador II', 'San Jose', 'San Juan', 'San Lorenzo Ruiz I', 'San Lorenzo Ruiz II', 'San Luis I', 'San Luis II', 'San Manuel I', 'San Manuel II', 'San Simon', 'Santa Cristina I', 'Santa Cristina II', 'Santa Cruz I', 'Santa Cruz II', 'Santa Fe', 'Santa Lucia', 'Santa Maria', 'Santo Cristo', 'Santo Nino I', 'Santo Nino II', 'Tiakag I', 'Tiakag II', 'Victoria Reyes', 'Zone I', 'Zone I-A', 'Zone I-B', 'Zone II', 'Zone III', 'Zone IV'],
        'General Trias City' => ['Alingaro', 'Arnaldo', 'Bacao I', 'Bacao II', 'Bagumbayan', 'Biclatan', 'Buenavista I', 'Buenavista II', 'Buenavista III', 'Corregidor', 'Dulong Bayan', 'Gov. Ferrer', 'Javalera', 'Manggahan', 'Navarro', 'Ninety Arasol', 'Panungyanan', 'Pasong Camachile I', 'Pasong Camachile II', 'Pasong Kawayan I', 'Pasong Kawayan II', 'Pinagtipunan', 'Poblacion I', 'Poblacion II', 'Poblacion III', 'Rosario', 'San Francisco', 'San Gabriel', 'San Juan I', 'San Juan II', 'Santa Clara', 'Santiago', 'Tapia', 'Tejero'],
        'Imus City' => ['Alapan I-A', 'Alapan I-B', 'Alapan I-C', 'Alapan II-A', 'Alapan II-B', 'Anabu I-A', 'Anabu I-B', 'Anabu I-C', 'Anabu I-D', 'Anabu I-E', 'Anabu I-F', 'Anabu I-G', 'Anabu II-A', 'Anabu II-B', 'Anabu II-C', 'Anabu II-D', 'Anabu II-E', 'Anabu II-F', 'Bayan Luma I', 'Bayan Luma II', 'Bayan Luma III', 'Bayan Luma IV', 'Bayan Luma V', 'Bayan Luma VI', 'Bayan Luma VII', 'Bayan Luma VIII', 'Bayan Luma IX', 'Bucandala I', 'Bucandala II', 'Bucandala III', 'Bucandala IV', 'Bucandala V', 'Buhay na Tubig', 'Carsadang Bago I', 'Carsadang Bago II', 'Magdalo', 'Maharlika', 'Mariano Espeleta I', 'Mariano Espeleta II', 'Mariano Espeleta III', 'Medicion I-A', 'Medicion I-B', 'Medicion I-C', 'Medicion I-D', 'Medicion II-A', 'Medicion II-B', 'Medicion II-C', 'Medicion II-D', 'Medicion II-E', 'Medicion II-F', 'Palico I', 'Palico II', 'Palico III', 'Palico IV', 'Pasong Buaya I', 'Pasong Buaya II', 'Pinagbuklod', 'Poblacion I-A', 'Poblacion I-B', 'Poblacion I-C', 'Poblacion II-A', 'Poblacion II-B', 'Poblacion III-A', 'Poblacion III-B', 'Poblacion IV-A', 'Poblacion IV-B', 'Poblacion IV-C', 'Poblacion IV-D', 'Tanzang Luma I', 'Tanzang Luma II', 'Tanzang Luma III', 'Tanzang Luma IV', 'Tanzang Luma V', 'Tanzang Luma VI', 'Toclong I-A', 'Toclong I-B', 'Toclong I-C', 'Toclong II-A', 'Toclong II-B'],
        'Kawit' => ['Binakayan', 'Bued', 'Gahak', 'Kaingen', 'Magdalo', 'Manalo', 'Panamitan', 'Poblacion', 'Pulvorista', 'Salinas', 'San Sebastian', 'Santa Isabel', 'Tabon I', 'Tabon II', 'Tabon III', 'Toclong', 'Tramo', 'Wakas I', 'Wakas II'],
        'Noveleta' => ['Magdiwang', 'Poblacion', 'Salcedo I', 'Salcedo II', 'San Antonio I', 'San Antonio II', 'San Jose I', 'San Jose II', 'San Juan I', 'San Juan II', 'San Rafael I', 'San Rafael II', 'San Rafael III', 'San Rafael IV', 'Santa Rosa I', 'Santa Rosa II'],
        'Rosario' => ['Bagbag I', 'Bagbag II', 'Kanluran', 'Ligtong I', 'Ligtong II', 'Ligtong III', 'Ligtong IV', 'Muzon I', 'Muzon II', 'Poblacion', 'Sapa I', 'Sapa II', 'Sapa III', 'Sapa IV', 'Silangan I', 'Silangan II', 'Tejeros Convention', 'Wawa I', 'Wawa II', 'Wawa III']
    ],
    'Laguna' => [
        'Binan City' => ['Binan', 'Bungahan', 'Canlalay', 'Casile', 'De La Paz', 'Ganado', 'Langkiwa', 'Loma', 'Malaban', 'Malamig', 'Mampalasan', 'Platero', 'Poblacion', 'San Antonio', 'San Francisco', 'San Jose', 'San Vicente', 'Santo Domingo', 'Santo Nino', 'Santo Tomas', 'Soro-soro', 'Timbao', 'Tubigan', 'Zapote'],
        'Cabuyao City' => ['Baclaran', 'Banay-Banay', 'Banlic', 'Bigaa', 'Butong', 'Casile', 'Gulod', 'Mamatid', 'Marinig', 'Niugan', 'Pittland', 'Pulo', 'Sala', 'San Isidro'],
        'Calamba City' => ['Bagong Kalsada', 'Banadero', 'Banlic', 'Barandal', 'Barangay I', 'Barangay II', 'Barangay III', 'Barangay IV', 'Barangay V', 'Barangay VI', 'Barangay VII', 'Batino', 'Bubuyan', 'Bucal', 'Bunggo', 'Burol', 'Camaligan', 'Canlubang', 'Halang', 'Hornalan', 'Kay-anlog', 'La Mesa', 'Laguiroc', 'Lawa', 'Lecheria', 'Looc', 'Mabato', 'Majada Out', 'Makiling', 'Mapagong', 'Masili', 'Maunong', 'Mayapa', 'Milagrosa', 'Paciano Rizal', 'Palingon', 'Palo-Alto', 'Pansol', 'Parian', 'Prinza', 'Punta', 'Putho-Tuntungin', 'Real', 'Saimsim', 'Sampiruhan', 'San Cristobal', 'San Jose', 'San Juan', 'Sirang Lupa', 'Sucol', 'Turbina', 'Ulango', 'Uwisan'],
        'Los Banos' => ['Anos', 'Bagong Silang', 'Bambang', 'Batong Malake', 'Baybayin', 'Bayog', 'Lalakay', 'Maahas', 'Malinta', 'Mayondon', 'Putho', 'San Antonio', 'Tadlak', 'Tuntungin Putho', 'Timugan'],
        'San Pablo City' => ['Atisan', 'Bagong Bayan I', 'Bagong Bayan II', 'Bagong Pook', 'Barangay I', 'Barangay II', 'Barangay III', 'Barangay IV', 'Barangay V', 'Barangay VI', 'Barangay VII', 'Concepcion', 'Del Remedio', 'Dolores', 'San Antonio I', 'San Antonio II', 'San Buenaventura', 'San Crispin', 'San Cristobal', 'San Diego', 'San Francisco', 'San Gabriel', 'San Gregorio', 'San Ignacio', 'San Isidro', 'San Joaquin', 'San Jose', 'San Juan', 'San Lorenzo', 'San Lucas I', 'San Lucas II', 'San Marcos', 'San Mateo', 'San Miguel', 'San Nicolas', 'San Pedro', 'San Rafael', 'San Roque', 'San Vicente', 'Santa Ana', 'Santa Catalina', 'Santa Cruz', 'Santa Elena', 'Santa Filomena', 'Santa Isabel', 'Santa Maria', 'Santa Maria Magdalena', 'Santa Monica', 'Santa Veronica', 'Santiago I', 'Santiago II', 'Santo Angel', 'Santo Cristo', 'Santo Nino'],
        'Santa Rosa City' => ['Aplaya', 'Balibago', 'Caingin', 'Dila', 'Dita', 'Don Jose', 'Ibaba', 'Kanluran', 'Labas', 'Macabling', 'Malitlit', 'Malusak', 'Market Area', 'Pook', 'Pulong Santa Cruz', 'Santo Domingo', 'Sinalhan', 'Tagapo', 'Silang Junction North', 'Silang Junction South', 'Balanti']
    ],
    'Rizal' => [
        'Antipolo City' => ['Bagong Nayon', 'Beverly Hills', 'Calawis', 'Cupang', 'Dalig', 'Dela Paz', 'Inarawan', 'Mambugan', 'Mayamot', 'Muntingdilaw', 'Olingan', 'Pajo', 'Pinugay', 'San Isidro', 'San Jose', 'San Juan', 'San Luis', 'San Roque', 'Santa Cruz', 'Sta. Maria'],
        'Cainta' => ['San Andres', 'San Antonio', 'San Isidro', 'San Juan', 'Santa Rosa', 'Santo Domingo', 'Santo Nino'],
        'Taytay' => ['Dolores', 'Muzon', 'San Isidro', 'San Juan', 'Santa Ana', 'Tipas'],
        'Rodriguez' => ['Balite', 'Burgos', 'Geronimo', 'Macabud', 'Manggahan', 'Mascap', 'Montalban', 'Puray', 'Rosario', 'San Isidro', 'San Jose', 'San Rafael']
    ],
    'Pampanga' => [
        'Angeles City' => ['Agapito del Rosario', 'Amsic', 'Anunas', 'Balibago', 'Capaya', 'Claro M. Recto', 'Cuayan', 'Cutcut', 'Cutud', 'Lourdes North West', 'Lourdes Sur', 'Lourdes Sur East', 'Malabanias', 'Margot', 'Marisol', 'Mining', 'Ninoy Aquino', 'Pampang', 'Pandan', 'Pulung Bulu', 'Pulung Cacutud', 'Pulung Maragul', 'Salapungan', 'San Jose', 'San Nicolas', 'Santa Teresita', 'Santa Trinidad', 'Santo Cristo', 'Santo Domingo', 'Santo Rosario', 'Sapalibutad', 'Sapangbato', 'Tabun', 'Virgen delos Remedios'],
        'San Fernando City' => ['Alasas', 'Baliti', 'Bulaon', 'Calulut', 'Del Carmen', 'Del Pilar', 'Del Rosario', 'Dolores', 'Juliana', 'Lara', 'Lourdes', 'Magliman', 'Maimpis', 'Malino', 'Malpitic', 'Pandaras', 'Panipuan', 'Pulung Bulo', 'Quebiawan', 'Saguin', 'San Agustin', 'San Felipe', 'San Isidro', 'San Jose', 'San Juan', 'San Nicolas', 'San Pedro Cutud', 'Santa Lucia', 'Santa Teresita', 'Santo Nino', 'Santo Rosario', 'Sindalan', 'Telabastagan'],
        'Guagua' => ['Ascomo', 'Bancal', 'Jose Abad Santos', 'Plaza Burgos', 'Poblacion', 'San Agustin', 'San Antonio', 'San Isidro', 'San Jose', 'San Juan', 'San Matias', 'San Nicolas', 'San Pablo', 'San Pedro', 'San Rafael', 'San Roque', 'San Vicente', 'Santa Filomena', 'Santa Ines', 'Santa Ursula', 'Santo Cristo', 'Santo Nino']
    ],
    'Batangas' => [
        'Batangas City' => ['Alangilan', 'Balagtas', 'Balete', 'Banaba Center', 'Banaba South', 'Banaba West', 'Bilogo', 'Bolbok', 'Bukal', 'Calicanto', 'Catandala', 'Conde Itaas', 'Conde Labak', 'Cuta', 'Dalig', 'Dela Paz', 'Dela Paz Proper', 'Dela Paz Pulot Aplaya', 'Dela Paz Pulot Itaas', 'Kumintang Ibaba', 'Kumintang Ilaya', 'Libjo', 'Liponpon', 'Mahabang Dahilig', 'Malitam', 'Pallocan Kanluran', 'Pallocan Silangan', 'Pinamucan', 'Pinamucan Ibaba', 'Poblacion', 'San Isidro', 'San Jose Sico', 'San Miguel', 'San Pedro', 'Santa Clara', 'Santa Rita Aplaya', 'Santa Rita Karsada', 'Santo Domingo', 'Simlong', 'Sorosoro Ibaba', 'Sorosoro Ilaya', 'Tabangao Aplaya', 'Tabangao Ambulong', 'Tabangao Dao', 'Talumpok Kanluran', 'Talumpok Silangan', 'Tinga Itaas', 'Tinga Labak', 'Wawa'],
        'Lipa City' => ['Adya', 'Anilao', 'Bagong Pook', 'Balintawak', 'Banaybanay', 'Bolbok', 'Bugtong na Pulo', 'Bulacnin', 'Bulaknin', 'Calamias', 'Cumba', 'Dagatan', 'Duhatan', 'Halang', 'Inosloban', 'Lumbang', 'Mabini', 'Marawoy', 'Marauoy', 'Mataas na Lupa', 'Munting Pulo', 'Pangao', 'Pilahan', 'Pinagkawitan', 'Pinagtongulan', 'Plaridel', 'Poblacion', 'Sabang', 'Sampaguita', 'San Benito', 'San Carlos', 'San Celestino', 'San Francisco', 'San Guillermo', 'San Jose', 'San Lucas', 'San Salvador', 'San Sebastian', 'Santa Cruz', 'Santo Nino', 'Santo Toribio', 'Sapac', 'Sico', 'Talisay', 'Tambo', 'Tangob', 'Tanguay', 'Tibig', 'Tipakan'],
        'Tanauan City' => ['Ambulong', 'Bagumbayan', 'Bagumabayan East', 'Bilog-Bilog', 'Boot', 'Darasa', 'Gonzales', 'Hidalgo', 'Janopol', 'Laurel', 'Luyos', 'Mabini', 'Makawayan', 'Malaking Pulo', 'Maria Paz', 'Maugat', 'Natatas', 'Pagaspas', 'Pantay', 'Poblacion Barangay 1', 'Poblacion Barangay 2', 'Poblacion Barangay 3', 'Poblacion Barangay 4', 'Poblacion Barangay 5', 'Poblacion Barangay 6', 'Poblacion Barangay 7', 'Sala', 'Sambat', 'San Jose', 'Santor', 'Saysain', 'Sentang', 'Sulpoc', 'Talaga', 'Tinurik', 'Trapiche', 'Ulango', 'Wawa']
    ],
    'Cebu' => [
        'Cebu City' => ['Adlaon', 'Agsungot', 'Apas', 'Babag', 'Bacayan', 'Banilad', 'Basak Pardo', 'Basak San Nicolas', 'Binaliw', 'Bonbon', 'Budla-an', 'Buhisan', 'Bulacao', 'Busay', 'Calamba', 'Camputhaw', 'Capitol Site', 'Carreta', 'Cogon Ramos', 'Cogon Pardo', 'Day-as', 'Duljo-Fatima', 'Ermita', 'Guadalupe', 'Guba', 'Hipodromo', 'Inayawan', 'Kalubihan', 'Kalunasan', 'Kamagayan', 'Kamputhaw', 'Kasambagan', 'Kinasang-an', 'Labangon', 'Lahug', 'Lorega San Miguel', 'Luz', 'Mabini', 'Mabolo', 'Malubog', 'Mambaling', 'Pahina Central', 'Pahina San Nicolas', 'Pamutan', 'Pardo', 'Parian', 'Paril', 'Pasil', 'Pit-os', 'Poblacion Pardo', 'Pulangbato', 'Pung-ol Sibugay', 'Punta Princesa', 'Quiot', 'Sambag I', 'Sambag II', 'San Antonio', 'San Jose', 'San Nicolas Central', 'San Nicolas Proper', 'San Roque', 'Santa Cruz', 'Sawang Calero', 'Sinsin', 'Sirao', 'Suba', 'Sudlon I', 'Sudlon II', 'T. Padilla', 'Tabunan', 'Tagbao', 'Talamban', 'Taptap', 'Tejero', 'Tinago', 'Tisa', 'To-ong', 'Zapatera'],
        'Lapu-Lapu City' => ['Agus', 'Babag', 'Bankal', 'Basak', 'Buaya', 'Calawisan', 'Canjulao', 'Caohagan', 'Caubian', 'Gun-ob', 'Ibo', 'Looc', 'Maribago', 'Marigondon', 'Pajac', 'Pajo', 'Pangan-an', 'Poblacion', 'Punta Engano', 'Pusok', 'Sabang', 'Santa Rosa', 'Subabasbas', 'Talima', 'Tingo', 'Tungasan'],
        'Mandaue City' => ['Alang-alang', 'Bakilid', 'Banilad', 'Basak', 'Cabancalan', 'Cambaro', 'Canduman', 'Casili', 'Casuntingan', 'Centro', 'Cubacub', 'Guizo', 'Ibabao-Estancia', 'Jagobiao', 'Labogon', 'Looc', 'Maguikay', 'Mantuyong', 'Opao', 'Pakna-an', 'Pagsabungan', 'Subangdaku', 'Tabok', 'Tawason', 'Tingub', 'Tipolo', 'Umapad']
    ],
    'Davao' => [
        'Davao City' => ['Poblacion', 'Talomo', 'Agdao', 'Buhangin', 'Bunawan', 'Calinan', 'Marilog', 'Paquibato', 'Toril', 'Tugbok', 'Baguio', 'Catalunan Grande', 'Catalunan Pequeno', 'Langub', 'Ma-a', 'Magtuod', 'Matina Aplaya', 'Matina Crossing', 'Matina Pangi', 'Mintal', 'Sasa', 'Tibungco', 'Vicente Hizon']
    ]
];

include 'includes/header.php';
?>

<style>
    .events-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 25px;
        flex-wrap: wrap;
        gap: 15px;
    }

    .events-table th,
    .events-table td {
        vertical-align: middle;
        padding: 15px 12px;
    }

    .event-info {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .event-icon {
        width: 45px;
        height: 45px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: rgba(147, 112, 219, 0.15);
    }

    .event-icon i {
        font-size: 1.2rem;
        color: var(--bs-primary);
    }

    .action-buttons {
        display: flex;
        gap: 8px;
    }

    .action-btn {
        width: 36px;
        height: 36px;
        border-radius: 8px;
        border: none;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: 0.3s;
    }

    .btn-view {
        background: rgba(147, 112, 219, 0.15);
        color: var(--bs-primary);
    }

    .btn-view:hover {
        background: var(--bs-primary);
        color: white;
    }

    .btn-edit {
        background: rgba(255, 171, 0, 0.15);
        color: var(--bs-warning);
    }

    .btn-edit:hover {
        background: var(--bs-warning);
        color: #000;
    }

    .btn-delete {
        background: rgba(255, 62, 29, 0.15);
        color: var(--bs-danger);
    }

    .btn-delete:hover {
        background: var(--bs-danger);
        color: white;
    }

    .booking-form {
        background: var(--card-bg);
        border: 1px solid var(--border-color);
        border-radius: 16px;
        padding: 30px;
        margin-bottom: 30px;
    }

    .form-section-title {
        color: var(--bs-primary);
        font-weight: 700;
        font-size: 0.9rem;
        text-transform: uppercase;
        letter-spacing: 1px;
        margin-bottom: 20px;
        padding-bottom: 10px;
        border-bottom: 2px solid var(--bs-primary);
    }

    .form-row {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
        margin-bottom: 20px;
    }

    .backdrop-preview {
        max-width: 150px;
        border-radius: 10px;
        border: 2px solid var(--bs-primary);
    }

    .filter-bar {
        display: flex;
        gap: 12px;
        margin-bottom: 25px;
        flex-wrap: wrap;
    }

    .filter-btn {
        padding: 10px 18px;
        border-radius: 8px;
        border: 1px solid var(--border-color);
        background: transparent;
        color: var(--body-color);
        font-weight: 600;
        cursor: pointer;
        transition: 0.3s;
    }

    .filter-btn:hover,
    .filter-btn.active {
        background: var(--bs-primary);
        border-color: var(--bs-primary);
        color: white;
    }

    .select-backdrop-btn {
        background: rgba(147, 112, 219, 0.15);
        border: 1px dashed var(--bs-primary);
        color: var(--bs-primary);
        padding: 12px 20px;
        border-radius: 10px;
        font-weight: 700;
        cursor: pointer;
        transition: 0.3s;
        display: inline-block;
        text-decoration: none;
    }

    .select-backdrop-btn:hover {
        background: var(--bs-primary);
        color: white;
        border-style: solid;
    }

    .inventory-note {
        background: rgba(147, 112, 219, 0.1);
        border: 1px solid rgba(147, 112, 219, 0.3);
        border-radius: 10px;
        padding: 16px;
        margin-bottom: 20px;
    }

    .inventory-note h6 {
        color: var(--bs-primary);
        font-weight: 700;
        margin-bottom: 8px;
    }

    .inventory-note p {
        margin: 0;
        font-size: 0.875rem;
        color: var(--body-color);
    }
</style>

<?php if ($action === 'add' || $action === 'edit'): ?>

<!-- Booking Form -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="page-title mb-0"><?php echo $action === 'edit' ? 'Edit Event' : 'New Event Booking'; ?></h4>
    <a href="events.php" class="btn btn-secondary">
        <i class="bx bx-arrow-back me-2"></i>Back to Events
    </a>
</div>

<!-- Inventory Note -->
<div class="inventory-note">
    <h6><i class="bx bx-info-circle me-2"></i>Automatic Inventory Deduction</h6>
    <p>When you create a new event booking, inventory items will be automatically deducted based on the number of guests (pax). This includes silverware, dinnerware, glassware, and linens.</p>
</div>

<div class="booking-form">
    <form method="POST" id="eventForm">
        <input type="hidden" name="form_action" value="<?php echo $action === 'edit' ? 'edit_event' : 'add_event'; ?>" id="formAction">
        <?php if ($edit_event): ?>
        <input type="hidden" name="event_id" value="<?php echo $edit_event['id']; ?>">
        <?php endif; ?>

        <div class="form-section-title"><i class="bx bx-user me-2"></i>Customer Information</div>
        <div class="form-row">
            <div>
                <label class="form-label">Full Name <span class="text-danger">*</span></label>
                <input type="text" name="fullname" id="fullname" class="form-control" required 
                       value="<?php echo htmlspecialchars($edit_event['fullname'] ?? $form_data['fullname'] ?? ''); ?>" 
                       placeholder="Customer full name">
            </div>
            <div>
                <label class="form-label">Email Address</label>
                <input type="email" name="email" id="email" class="form-control" 
                       value="<?php echo htmlspecialchars($edit_event['email'] ?? $form_data['email'] ?? ''); ?>" 
                       placeholder="customer@email.com">
            </div>
            <div>
                <label class="form-label">Contact Number</label>
                <input type="text" name="contact" id="contact" class="form-control" 
                       value="<?php echo htmlspecialchars($edit_event['contact'] ?? $form_data['contact'] ?? ''); ?>" 
                       placeholder="09XX-XXX-XXXX">
            </div>
            <div>
                <label class="form-label">Home Address</label>
                <input type="text" name="customer_address" id="customer_address" class="form-control" 
                       value="<?php echo htmlspecialchars($edit_event['customer_address'] ?? $form_data['customer_address'] ?? ''); ?>" 
                       placeholder="Customer home address">
            </div>
        </div>

        <div class="form-section-title"><i class="bx bx-calendar-event me-2"></i>Event Details</div>
        <div class="form-row">
            <div>
                <label class="form-label">Event Type / Name <span class="text-danger">*</span></label>
                <select name="event_name" id="event_name" class="form-select" required>
                    <option value="">Select Event Type</option>
                    <?php foreach ($event_types as $type): ?>
                    <option value="<?php echo $type; ?>" <?php echo (($edit_event['event_name'] ?? $form_data['event_name'] ?? '') === $type) ? 'selected' : ''; ?>>
                        <?php echo $type; ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="form-label">Event Date <span class="text-danger">*</span></label>
                <input type="date" name="event_date" id="event_date" class="form-control" required 
                       value="<?php echo $edit_event['event_date'] ?? $form_data['event_date'] ?? ''; ?>">
            </div>
            <div>
                <label class="form-label">Number of Pax <span class="text-danger">*</span></label>
                <input type="number" name="pax" id="pax" class="form-control" min="50" required 
                       value="<?php echo $edit_event['pax'] ?? $form_data['pax'] ?? '50'; ?>" 
                       placeholder="Minimum 50">
                <small class="text-muted">Inventory will be deducted based on this number</small>
            </div>
            <?php if ($action === 'edit'): ?>
            <div>
                <label class="form-label">Status</label>
                <select name="status" class="form-select">
                    <option value="Pending" <?php echo ($edit_event['status'] ?? '') === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="Confirmed" <?php echo ($edit_event['status'] ?? '') === 'Confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                    <option value="Completed" <?php echo ($edit_event['status'] ?? '') === 'Completed' ? 'selected' : ''; ?>>Completed</option>
                    <option value="Cancelled" <?php echo ($edit_event['status'] ?? '') === 'Cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                </select>
            </div>
            <?php endif; ?>
        </div>

        <div class="form-section-title"><i class="bx bx-map me-2"></i>Venue Address (Philippine Address)</div>
        <div class="form-row">
            <div>
                <label class="form-label">Province / Region <span class="text-danger">*</span></label>
                <select name="province" id="province" class="form-select" onchange="updateCities()" required>
                    <option value="">Select Province</option>
                    <?php foreach (array_keys($ph_address_data) as $province): ?>
                    <option value="<?php echo $province; ?>" <?php echo (($edit_event['province'] ?? $form_data['province'] ?? '') === $province) ? 'selected' : ''; ?>>
                        <?php echo $province; ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="form-label">City / Municipality <span class="text-danger">*</span></label>
                <select name="city" id="city" class="form-select" onchange="updateBarangays()" required>
                    <option value="">Select City</option>
                </select>
            </div>
            <div>
                <label class="form-label">Barangay <span class="text-danger">*</span></label>
                <select name="barangay" id="barangay" class="form-select" required>
                    <option value="">Select Barangay</option>
                </select>
            </div>
        </div>
        <div class="mb-4">
            <label class="form-label">Full Venue Address / Landmark</label>
            <textarea name="venue_address" id="venue_address" class="form-control" rows="2" 
                      placeholder="e.g., Blue Gardens Event Place, near Jollibee Guiguinto"><?php echo htmlspecialchars($edit_event['venue_address'] ?? $form_data['venue_address'] ?? ''); ?></textarea>
        </div>

        <div class="form-section-title"><i class="bx bx-image me-2"></i>Selected Backdrop</div>
        <div class="mb-4">
            <?php 
            $backdrop = $selected_backdrop ?: ($edit_event['backdrop'] ?? '');
            if ($backdrop): 
            ?>
            <div class="d-flex align-items-center gap-4 flex-wrap">
                <img src="static/images/<?php echo htmlspecialchars($backdrop); ?>" class="backdrop-preview" alt="Selected Backdrop">
                <div>
                    <p style="margin: 0; font-weight: 700; color: var(--heading-color);">Selected: <?php echo htmlspecialchars($backdrop); ?></p>
                    <button type="button" class="select-backdrop-btn mt-2" onclick="goToBackdrops()">
                        <i class="bx bx-transfer me-2"></i>Change Backdrop
                    </button>
                </div>
            </div>
            <input type="hidden" name="backdrop" value="<?php echo htmlspecialchars($backdrop); ?>">
            <?php else: ?>
            <p class="text-muted mb-3">No backdrop selected yet.</p>
            <button type="button" class="select-backdrop-btn" onclick="goToBackdrops()">
                <i class="bx bx-image me-2"></i>Select Backdrop
            </button>
            <input type="hidden" name="backdrop" value="">
            <?php endif; ?>
        </div>

        <div class="d-flex gap-3">
            <button type="submit" class="btn btn-primary">
                <i class="bx bx-save me-2"></i><?php echo $action === 'edit' ? 'Update Event' : 'Save Booking'; ?>
            </button>
            <a href="events.php" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>

<script>
    const addressData = <?php echo json_encode($ph_address_data); ?>;
    const savedCity = '<?php echo $edit_event['city'] ?? $form_data['city'] ?? ''; ?>';
    const savedBarangay = '<?php echo $edit_event['barangay'] ?? $form_data['barangay'] ?? ''; ?>';
    
    function updateCities() {
        const province = document.getElementById('province').value;
        const citySelect = document.getElementById('city');
        citySelect.innerHTML = '<option value="">Select City</option>';
        document.getElementById('barangay').innerHTML = '<option value="">Select Barangay</option>';
        
        if (addressData[province]) {
            Object.keys(addressData[province]).sort().forEach(city => {
                const option = document.createElement('option');
                option.value = city;
                option.textContent = city;
                if (city === savedCity) option.selected = true;
                citySelect.appendChild(option);
            });
        }
        
        if (savedCity) {
            updateBarangays();
        }
    }

    function updateBarangays() {
        const province = document.getElementById('province').value;
        const city = document.getElementById('city').value;
        const barangaySelect = document.getElementById('barangay');
        barangaySelect.innerHTML = '<option value="">Select Barangay</option>';
        
        if (addressData[province] && addressData[province][city]) {
            addressData[province][city].sort().forEach(barangay => {
                const option = document.createElement('option');
                option.value = barangay;
                option.textContent = barangay;
                if (barangay === savedBarangay) option.selected = true;
                barangaySelect.appendChild(option);
            });
        }
    }

    function goToBackdrops() {
        document.getElementById('formAction').value = 'save_form_data';
        document.getElementById('eventForm').submit();
    }

    document.addEventListener('DOMContentLoaded', function() {
        const province = document.getElementById('province').value;
        if (province) {
            updateCities();
        }
    });
</script>

<?php else: ?>

<!-- Events List View -->
<div class="events-header">
    <h4 class="page-title mb-0">Events Management</h4>
    <a href="events.php?action=add" class="btn btn-primary">
        <i class="bx bx-plus me-2"></i>New Booking
    </a>
</div>

<!-- Filter Bar -->
<div class="filter-bar">
    <button class="filter-btn active" onclick="filterEvents('all')">All Events</button>
    <button class="filter-btn" onclick="filterEvents('Pending')">Pending</button>
    <button class="filter-btn" onclick="filterEvents('Confirmed')">Confirmed</button>
    <button class="filter-btn" onclick="filterEvents('Completed')">Completed</button>
    <button class="filter-btn" onclick="filterEvents('Cancelled')">Cancelled</button>
</div>

<?php if (empty($events)): ?>
<div class="card">
    <div class="card-body text-center py-5">
        <i class="bx bx-calendar" style="font-size: 4rem; color: var(--body-color); opacity: 0.3;"></i>
        <h5 class="mt-3" style="color: var(--heading-color);">No Events Found</h5>
        <p class="text-muted mb-4">Start by creating your first event booking!</p>
        <a href="events.php?action=add" class="btn btn-primary">
            <i class="bx bx-plus me-2"></i>Create New Event
        </a>
    </div>
</div>
<?php else: ?>
<div class="card">
    <div class="table-responsive">
        <table class="table table-hover events-table">
            <thead>
                <tr>
                    <th>Event Details</th>
                    <th>Customer</th>
                    <th>Date</th>
                    <th>Venue</th>
                    <th class="text-center">Pax</th>
                    <th class="text-center">Status</th>
                    <th class="text-center">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($events as $event): ?>
                <tr class="event-row" data-status="<?php echo $event['status']; ?>">
                    <td>
                        <div class="event-info">
                            <div class="event-icon">
                                <i class="bx bx-calendar-event"></i>
                            </div>
                            <div>
                                <strong style="color: var(--heading-color);"><?php echo htmlspecialchars($event['event_name']); ?></strong>
                                <?php if ($event['backdrop']): ?>
                                <p class="mb-0 text-muted" style="font-size: 0.75rem;">
                                    <i class="bx bx-image"></i> <?php echo htmlspecialchars($event['backdrop']); ?>
                                </p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </td>
                    <td>
                        <strong style="color: var(--heading-color);"><?php echo htmlspecialchars($event['fullname']); ?></strong>
                        <?php if ($event['contact']): ?>
                        <p class="mb-0 text-muted" style="font-size: 0.8rem;"><?php echo htmlspecialchars($event['contact']); ?></p>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span style="color: var(--heading-color); font-weight: 500;"><?php echo date('M d, Y', strtotime($event['event_date'])); ?></span>
                    </td>
                    <td>
                        <span style="font-size: 0.875rem;">
                            <?php 
                            $location = [];
                            if ($event['barangay']) $location[] = $event['barangay'];
                            if ($event['city']) $location[] = $event['city'];
                            if ($event['province']) $location[] = $event['province'];
                            echo htmlspecialchars(implode(', ', $location) ?: '-');
                            ?>
                        </span>
                    </td>
                    <td class="text-center">
                        <span class="badge badge-info" style="font-size: 0.9rem; font-weight: 600;">
                            <?php echo number_format($event['pax']); ?>
                        </span>
                    </td>
                    <td class="text-center">
                        <span class="status-pill status-<?php echo strtolower($event['status']); ?>">
                            <?php echo $event['status']; ?>
                        </span>
                    </td>
                    <td class="text-center">
                        <div class="action-buttons">
                            <button type="button" class="action-btn btn-view" onclick="viewEvent(<?php echo htmlspecialchars(json_encode($event)); ?>)" title="View Details">
                                <i class="bx bx-show"></i>
                            </button>
                            <a href="events.php?action=edit&id=<?php echo $event['id']; ?>" class="action-btn btn-edit" title="Edit">
                                <i class="bx bx-edit"></i>
                            </a>
                            <button type="button" class="action-btn btn-delete" onclick="deleteEvent(<?php echo $event['id']; ?>, '<?php echo htmlspecialchars(addslashes($event['event_name'])); ?>')" title="Delete">
                                <i class="bx bx-trash"></i>
                            </button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- View Event Modal -->
<div class="modal fade" id="viewEventModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bx bx-calendar-event me-2" style="color: var(--bs-primary);"></i>Event Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="eventDetailsBody">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Delete Event Modal -->
<div class="modal fade" id="deleteEventModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bx bx-trash me-2" style="color: var(--bs-danger);"></i>Delete Event</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="form_action" value="delete_event">
                <input type="hidden" name="event_id" id="delete_event_id">
                <div class="modal-body">
                    <div class="text-center mb-3">
                        <div style="width: 80px; height: 80px; background: rgba(255, 62, 29, 0.15); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 16px;">
                            <i class="bx bx-trash" style="font-size: 2.5rem; color: var(--bs-danger);"></i>
                        </div>
                        <h5 style="color: var(--heading-color);">Are you sure?</h5>
                        <p class="mb-0 text-muted">You are about to delete event "<strong id="delete_event_name"></strong>".</p>
                        <p class="text-danger mt-2"><small>This action cannot be undone.</small></p>
                    </div>
                </div>
                <div class="modal-footer justify-content-center">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">
                        <i class="bx bx-trash me-1"></i> Delete Event
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function filterEvents(status) {
    document.querySelectorAll('.filter-btn').forEach(btn => btn.classList.remove('active'));
    event.target.classList.add('active');
    
    document.querySelectorAll('.event-row').forEach(row => {
        if (status === 'all' || row.dataset.status === status) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}

function viewEvent(event) {
    const body = document.getElementById('eventDetailsBody');
    body.innerHTML = `
        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="text-muted" style="font-size: 0.75rem; text-transform: uppercase;">Event Name</label>
                <p style="color: var(--heading-color); font-weight: 600; margin: 0;">${event.event_name}</p>
            </div>
            <div class="col-md-6 mb-3">
                <label class="text-muted" style="font-size: 0.75rem; text-transform: uppercase;">Status</label>
                <p style="margin: 0;"><span class="status-pill status-${event.status.toLowerCase()}">${event.status}</span></p>
            </div>
            <div class="col-md-6 mb-3">
                <label class="text-muted" style="font-size: 0.75rem; text-transform: uppercase;">Customer Name</label>
                <p style="color: var(--heading-color); font-weight: 500; margin: 0;">${event.fullname}</p>
            </div>
            <div class="col-md-6 mb-3">
                <label class="text-muted" style="font-size: 0.75rem; text-transform: uppercase;">Contact</label>
                <p style="color: var(--heading-color); margin: 0;">${event.contact || '-'}</p>
            </div>
            <div class="col-md-6 mb-3">
                <label class="text-muted" style="font-size: 0.75rem; text-transform: uppercase;">Email</label>
                <p style="color: var(--heading-color); margin: 0;">${event.email || '-'}</p>
            </div>
            <div class="col-md-6 mb-3">
                <label class="text-muted" style="font-size: 0.75rem; text-transform: uppercase;">Event Date</label>
                <p style="color: var(--heading-color); font-weight: 500; margin: 0;">${new Date(event.event_date).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' })}</p>
            </div>
            <div class="col-md-6 mb-3">
                <label class="text-muted" style="font-size: 0.75rem; text-transform: uppercase;">Number of Guests</label>
                <p style="margin: 0;"><span class="badge badge-primary" style="font-size: 1rem;">${parseInt(event.pax).toLocaleString()} pax</span></p>
            </div>
            <div class="col-md-6 mb-3">
                <label class="text-muted" style="font-size: 0.75rem; text-transform: uppercase;">Backdrop</label>
                <p style="color: var(--heading-color); margin: 0;">${event.backdrop || 'None selected'}</p>
            </div>
            <div class="col-12 mb-3">
                <label class="text-muted" style="font-size: 0.75rem; text-transform: uppercase;">Venue Address</label>
                <p style="color: var(--heading-color); margin: 0;">
                    ${[event.barangay, event.city, event.province].filter(Boolean).join(', ') || '-'}
                    ${event.venue_address ? '<br><small class="text-muted">' + event.venue_address + '</small>' : ''}
                </p>
            </div>
            ${event.backdrop ? `
            <div class="col-12">
                <label class="text-muted" style="font-size: 0.75rem; text-transform: uppercase;">Backdrop Preview</label>
                <div class="mt-2">
                    <img src="static/images/${event.backdrop}" alt="Backdrop" style="max-width: 200px; border-radius: 10px; border: 2px solid var(--border-color);">
                </div>
            </div>
            ` : ''}
        </div>
    `;
    
    const modal = new bootstrap.Modal(document.getElementById('viewEventModal'));
    modal.show();
}

function deleteEvent(id, name) {
    document.getElementById('delete_event_id').value = id;
    document.getElementById('delete_event_name').textContent = name;
    
    const modal = new bootstrap.Modal(document.getElementById('deleteEventModal'));
    modal.show();
}
</script>

<?php endif; ?>

<?php include 'includes/footer.php'; ?>
