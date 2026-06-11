    </div>
</main>
<script>
    // Auto-refrescar les pàgines cada 60 segons si s'indica
    if (document.body.dataset.autorefresh) {
        setTimeout(() => location.reload(), 60000);
    }
    // Tancar alertes flash
    document.querySelectorAll('[data-dismiss]').forEach(el => {
        el.addEventListener('click', () => el.remove());
    });
</script>
</body>
</html>
