function confirmDelete(url){
    return confirm("Yakin ingin menghapus data ini?") ? window.location.href=url : false;
}

// Hamburger toggle
const hamburger = document.getElementById('hamburgerBtn');
const sidebar = document.getElementById('sidebar');
const overlay = document.getElementById('overlay');
const closeBtn = document.getElementById('closeSidebar');

function openSidebar(){
    sidebar.classList.add('active');
    overlay.classList.add('active');
    hamburger.classList.add('shifted'); // geser hamburger
}

function closeSidebar(){
    sidebar.classList.remove('active');
    overlay.classList.remove('active');
    hamburger.classList.remove('shifted'); // balik posisi
}

hamburger.addEventListener('click', () => {
    if(sidebar.classList.contains('active')){
        closeSidebar();
    } else {
        openSidebar();
    }
});
closeBtn.addEventListener('click', closeSidebar);
overlay.addEventListener('click', closeSidebar);

// Dropdown toggle
document.querySelectorAll('.dropdown-toggle').forEach(toggle => {
    toggle.addEventListener('click', function(e){
        e.preventDefault();
        toggle.classList.toggle('active');
        const submenu = toggle.nextElementSibling;
        if(submenu){
            submenu.style.display = submenu.style.display === 'block' ? 'none' : 'block';
        }
    });
});

// Ekspor Excel
document.getElementById('exportExcel').addEventListener('click', function(){
    var wb = XLSX.utils.table_to_book(document.getElementById('tablePerusahaan'), {sheet:"Perusahaan"});
    XLSX.writeFile(wb, "Perusahaan.xlsx");
});