<?php
/**
 * Shared bottom shell: the #site-footer mount chrome.js fills, then wp_footer.
 * (Templates that want the global CTA band emit <div id="site-cta"></div>
 *  before calling this part.)
 */
?>
<div id="site-footer"></div>

<?php wp_footer(); ?>
</body>
</html>
