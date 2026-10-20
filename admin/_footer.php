        </main>
    </div>
</div>
<script src="<?= e(url('assets/js/app.js')) ?>?v=40" defer></script>
<script>
(function(){var b=document.querySelector('.admin-menu-toggle');var s=document.querySelector('.admin-sidebar');if(b&&s){b.addEventListener('click',function(){s.classList.toggle('open');});document.addEventListener('click',function(e){if(window.innerWidth<=980&&s.classList.contains('open')&&!s.contains(e.target)&&e.target!==b){s.classList.remove('open');}});}})();
</script>
</body>
</html>
