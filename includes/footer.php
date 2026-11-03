<?php
/**
 * includes/footer.php
 * ---------------------------------------------------------------------------
 * Bottom of every protected page. Closes the main content area, loads the
 * client-side JavaScript, and closes the document.
 * ---------------------------------------------------------------------------
 */
?>
    <footer class="app-foot no-print">
        Student Records Management System, Green Valley Secondary School
    </footer>
</div><!-- /.main -->

<script src="js/script.js"></script>
<script src="js/pwa.js" defer></script>
<script>
// Mobile sidebar toggle
(function(){
    var toggle = document.querySelector('.nav-toggle');
    var sidebar = document.getElementById('sidebar');
    var backdrop = document.querySelector('.nav-backdrop');
    if(!toggle || !sidebar) return;
    if(!backdrop){
        backdrop = document.createElement('div');
        backdrop.className = 'nav-backdrop';
        document.body.appendChild(backdrop);
    }
    function openSidebar(){
        sidebar.classList.add('open');
        backdrop.classList.add('show');
        document.body.classList.add('nav-open');
        toggle.setAttribute('aria-expanded','true');
    }
    function closeSidebar(){
        sidebar.classList.remove('open');
        backdrop.classList.remove('show');
        document.body.classList.remove('nav-open');
        toggle.setAttribute('aria-expanded','false');
    }
    toggle.addEventListener('click', function(){
        sidebar.classList.contains('open') ? closeSidebar() : openSidebar();
    });
    backdrop.addEventListener('click', closeSidebar);
})();
</script>
</body>
</html>
