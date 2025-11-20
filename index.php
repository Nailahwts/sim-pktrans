<?php
// Set judul halaman spesifik untuk file ini
$pageTitle = "Dashboard"; 

// Panggil header
include "template/header.php"; 
?>

<h2>Selamat datang, <?= htmlspecialchars($username) ?>!</h2>
<p>Gunakan menu di sidebar untuk navigasi.</p>

<div class="row mt-4 g-4">
    <div class="col-md-4">
        <div class="card text-white bg-primary">
            <div class="card-body">
                <h5 class="card-title"><i class="fas fa-database"></i> Master Data</h5>
                <p class="card-text">Kelola data master perusahaan, warehouse, dermaga, kendaraan, dan tanda tangan.</p>
                <a href="master/partner.php" class="btn btn-light btn-sm">Buka <i class="fas fa-arrow-right"></i></a>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card text-white bg-success">
            <div class="card-body">
                <h5 class="card-title"><i class="fas fa-exchange-alt"></i> Transaksi</h5>
                <p class="card-text">Kelola order kerja, sales order, dan input rekap data surat jalan.</p>
                <a href="transaksi/purchase_order.php" class="btn btn-light btn-sm">Buka <i class="fas fa-arrow-right"></i></a>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card text-white bg-info">
            <div class="card-body">
                <h5 class="card-title"><i class="fas fa-file-invoice"></i> Laporan</h5>
                <p class="card-text">Buat dan cetak laporan order kerja, invoice, dan kwitansi.</p>
                <a href="laporan/order_kerja.php" class="btn btn-light btn-sm">Buka <i class="fas fa-arrow-right"></i></a>
            </div>
        </div>
    </div>
</div>
<?php
// Panggil footer
include "template/footer.php"; 
?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Hamburger toggle
const hamburger = document.getElementById('hamburgerBtn');
const sidebar = document.getElementById('sidebar');
const overlay = document.getElementById('overlay');

function openSidebar(){
    sidebar.classList.add('active');
    overlay.classList.add('active');
    hamburger.classList.add('shifted');
}
function closeSidebar(){
    sidebar.classList.remove('active');
    overlay.classList.remove('active');
    hamburger.classList.remove('shifted');
}
hamburger.addEventListener('click', () => {
    if(sidebar.classList.contains('active')){
        closeSidebar();
    } else {
        openSidebar();
    }
});
overlay.addEventListener('click', closeSidebar);

// Dropdown toggle
document.querySelectorAll('.dropdown-toggle').forEach(toggle => {
    toggle.addEventListener('click', function(e){
        e.preventDefault();
        this.classList.toggle('active');
        const submenu = this.nextElementSibling;
        if(submenu){
            submenu.classList.toggle('active');
        }
    });
});
</script>

</body>
</html>