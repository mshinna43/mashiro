
    </div><!-- /page-body -->
  </div><!-- /main -->
</div><!-- /layout -->

<script>
/* ── Modal helpers ── */
function openModal(id) {
    document.getElementById(id).classList.add('open');
    document.body.style.overflow = 'hidden';
}
function closeModal(id) {
    document.getElementById(id).classList.remove('open');
    document.body.style.overflow = '';
}
document.querySelectorAll('.modal-bg').forEach(function(bg) {
    bg.addEventListener('click', function(e) {
        if (e.target === bg) closeModal(bg.id);
    });
});

/* ── Auto-dismiss alert ── */
setTimeout(function() {
    document.querySelectorAll('.alert').forEach(function(el) {
        el.style.transition = 'opacity .4s';
        el.style.opacity = '0';
        setTimeout(() => el.remove(), 400);
    });
}, 4000);

/* ── Confirm delete ── */
document.querySelectorAll('[data-confirm]').forEach(function(btn) {
    btn.addEventListener('click', function(e) {
        if (!confirm(this.dataset.confirm || 'Yakin ingin menghapus?')) e.preventDefault();
    });
});
</script>
</body>
</html>
