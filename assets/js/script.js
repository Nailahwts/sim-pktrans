// ===== Confirm Delete =====
function confirmDelete(url) {
    if (confirm("Apakah yakin ingin menghapus data ini?")) {
        window.location.href = url;
    }
    return false;
}

// ===== Sidebar Toggle =====
const hamburgerBtn = document.getElementById('hamburgerBtn');
const sidebar = document.getElementById('sidebar');
const overlay = document.getElementById('overlay');
const closeBtn = document.getElementById('closeSidebar');

hamburgerBtn.addEventListener('click', () => {
    sidebar.style.left = '0';
    overlay.style.display = 'block';
    hamburgerBtn.classList.add('hamburger-hidden'); // sembunyikan hamburger
});

closeBtn.addEventListener('click', () => {
    sidebar.style.left = '-250px'; // atau sesuai lebar sidebar
    overlay.style.display = 'none';
    hamburgerBtn.classList.remove('hamburger-hidden'); // tampilkan lagi
});

overlay.addEventListener('click', () => {
    sidebar.style.left = '-250px';
    overlay.style.display = 'none';
    hamburgerBtn.classList.remove('hamburger-hidden');
});

// ===== Dashboard Sidebar Toggle (alternate method) =====
function openSidebar() {
    sidebar.style.width = '250px';
    overlay.style.display = 'block';
    hamburgerBtn.style.display = 'none'; // wajib
}

function closeSidebar() {
    sidebar.style.width = '0';
    overlay.style.display = 'none';
    hamburgerBtn.style.display = 'block';
}

// Event listener
hamburgerBtn.addEventListener('click', openSidebar);
closeBtn.addEventListener('click', closeSidebar);
overlay.addEventListener('click', closeSidebar);

// ===== Dashboard interactions =====
document.addEventListener("DOMContentLoaded", function () {
    // Hover animasi kartu
    document.querySelectorAll(".card").forEach(card => {
        card.addEventListener("mouseenter", () => card.classList.add("shadow-lg"));
        card.addEventListener("mouseleave", () => card.classList.remove("shadow-lg"));
    });

    // Toast Welcome
    const toastHTML = `
    <div class="toast align-items-center text-bg-primary border-0 position-fixed bottom-0 end-0 m-3" 
         role="alert" aria-live="assertive" aria-atomic="true">
      <div class="d-flex">
        <div class="toast-body">
          Selamat datang di Dashboard PetroKaryaTrans 🚀
        </div>
        <button type="button" class="btn-close btn-close-white me-2 m-auto" 
                data-bs-dismiss="toast" aria-label="Close"></button>
      </div>
    </div>`;
    document.body.insertAdjacentHTML("beforeend", toastHTML);
    const toastEl = document.querySelector(".toast");
    new bootstrap.Toast(toastEl).show();

    // Counter Animation
    document.querySelectorAll(".card-text").forEach(el => {
        let target = parseInt(el.textContent);
        let count = 0;
        let step = Math.ceil(target / 50);
        let interval = setInterval(() => {
            count += step;
            if (count >= target) {
                el.textContent = target;
                clearInterval(interval);
            } else {
                el.textContent = count;
            }
        }, 40);
    });
});

