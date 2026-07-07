<?php
/**
 * Theme footer.
 *
 * Two variants:
 *  - Static-design pages (home, pricing) → static-design footer (white bg, 5-column).
 *  - All other pages → classic dark footer.
 */

if ( function_exists( 'instawp_use_static_design' ) && instawp_use_static_design() ) :
	?>
	<footer class="footer">
		<div class="container">
			<div class="footer-grid">

				<div class="footer-brand">
					<div class="footer-brand-name">
						<div class="logo-icon"></div>
						InstaWP
					</div>
					<p class="footer-tagline">The WordPress cloud built for people who ship. Sandbox to production in one click.</p>
					<div class="footer-social">
						<a href="https://twitter.com/InstaWP" aria-label="Twitter">
							<svg viewBox="0 0 24 24" fill="currentColor"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>
						</a>
						<a href="https://github.com/InstaWP" aria-label="GitHub">
							<svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.477 2 2 6.477 2 12c0 4.42 2.87 8.17 6.84 9.5.5.08.66-.23.66-.5v-1.69c-2.77.6-3.36-1.34-3.36-1.34-.46-1.16-1.11-1.47-1.11-1.47-.91-.62.07-.6.07-.6 1 .07 1.53 1.03 1.53 1.03.87 1.52 2.34 1.07 2.91.83.09-.65.35-1.09.63-1.34-2.22-.25-4.55-1.11-4.55-4.92 0-1.11.38-2 1.03-2.71-.1-.25-.45-1.29.1-2.64 0 0 .84-.27 2.75 1.02.79-.22 1.65-.33 2.5-.33.85 0 1.71.11 2.5.33 1.91-1.29 2.75-1.02 2.75-1.02.55 1.35.2 2.39.1 2.64.65.71 1.03 1.6 1.03 2.71 0 3.82-2.34 4.66-4.57 4.91.36.31.69.92.69 1.85V21c0 .27.16.59.67.5C19.14 20.16 22 16.42 22 12A10 10 0 0012 2z"/></svg>
						</a>
						<a href="https://www.youtube.com/@InstaWP" aria-label="YouTube">
							<svg viewBox="0 0 24 24" fill="currentColor"><path d="M23.498 6.186a3.016 3.016 0 00-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 00.502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 002.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 002.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/></svg>
						</a>
						<a href="#" aria-label="LinkedIn">
							<svg viewBox="0 0 24 24" fill="currentColor"><path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433c-1.144 0-2.063-.926-2.063-2.065 0-1.138.92-2.063 2.063-2.063 1.14 0 2.064.925 2.064 2.063 0 1.139-.925 2.065-2.064 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/></svg>
						</a>
					</div>
				</div>

				<div class="footer-col">
					<h4>Platform</h4>
					<ul>
						<li><a href="<?php echo esc_url( home_url( '/sandbox/' ) ); ?>">Sandbox</a></li>
						<li><a href="<?php echo esc_url( home_url( '/hosting/' ) ); ?>">Hosting</a></li>
						<li><a href="#">Snapshots</a></li>
						<li><a href="#">Templates</a></li>
						<li><a href="#">CLI &amp; API</a></li>
						<li><a href="<?php echo esc_url( home_url( '/plugins/' ) ); ?>">Plugins</a></li>
					</ul>
				</div>

				<div class="footer-col">
					<h4>Use Cases</h4>
					<ul>
						<li><a href="<?php echo esc_url( home_url( '/migrate/' ) ); ?>">Migrate WordPress</a></li>
						<li><a href="#">Two-way sync</a></li>
						<li><a href="#">White-label (WaaS)</a></li>
						<li><a href="#">Plugin demos</a></li>
						<li><a href="#">Multi-site management</a></li>
					</ul>
				</div>

				<div class="footer-col">
					<h4>For You</h4>
					<ul>
						<li><a href="<?php echo esc_url( home_url( '/for-agencies/' ) ); ?>">For Agencies</a></li>
						<li><a href="#">For Developers</a></li>
						<li><a href="#">For Plugin Authors</a></li>
						<li><a href="#">For Educators</a></li>
					</ul>
				</div>

				<div class="footer-col">
					<h4>Resources</h4>
					<ul>
						<li><a href="https://docs.instawp.com">Documentation</a></li>
						<li><a href="#">API Reference</a></li>
						<li><a href="#">Blog</a></li>
						<li><a href="#">Changelog</a></li>
						<li><a href="https://status.instawp.com">Status</a></li>
					</ul>
				</div>

				<div class="footer-col">
					<h4>Company</h4>
					<ul>
						<li><a href="<?php echo esc_url( home_url( '/about/' ) ); ?>">About</a></li>
						<li><a href="<?php echo esc_url( home_url( '/pricing/' ) ); ?>">Pricing</a></li>
						<li><a href="#">Contact</a></li>
						<li><a href="#">Careers</a></li>
						<li><a href="#">Partners</a></li>
					</ul>
				</div>
			</div>

			<div class="footer-bottom">
				<span>&copy; <?php echo esc_html( date( 'Y' ) ); ?> InstaWP. All rights reserved.</span>
				<div class="footer-bottom-links">
					<a href="#">Privacy</a>
					<a href="#">Terms</a>
					<a href="#">Security</a>
				</div>
			</div>
		</div>
	</footer>
	<?php
	return;
endif;

// Classic dark footer for non-front pages.
$footer_links = array(
	'Product'   => array(
		array( 'title' => 'WordPress Sandbox', 'url' => '/features/' ),
		array( 'title' => 'Staging Sites', 'url' => '/features/' ),
		array( 'title' => 'Site Templates', 'url' => '/features/' ),
		array( 'title' => 'Pricing', 'url' => '/pricing/' ),
	),
	'Use Cases' => array(
		array( 'title' => 'Agencies', 'url' => '/solutions/' ),
		array( 'title' => 'Developers', 'url' => '/solutions/' ),
		array( 'title' => 'Educators', 'url' => '/solutions/' ),
		array( 'title' => 'Plugin Authors', 'url' => '/solutions/' ),
	),
	'Resources' => array(
		array( 'title' => 'Blog', 'url' => '/blog/' ),
		array( 'title' => 'Documentation', 'url' => 'https://docs.instawp.com' ),
		array( 'title' => 'Changelog', 'url' => '/changelog/' ),
		array( 'title' => 'Status', 'url' => 'https://status.instawp.com' ),
	),
	'Company'   => array(
		array( 'title' => 'About', 'url' => '/about/' ),
		array( 'title' => 'Contact', 'url' => '/contact/' ),
		array( 'title' => 'Privacy Policy', 'url' => '/privacy/' ),
		array( 'title' => 'Terms of Service', 'url' => '/terms/' ),
	),
);
?>
<footer class="bg-[var(--color-dark)] text-white pt-16 pb-8">
	<div class="container">
		<div class="grid grid-cols-2 md:grid-cols-6 gap-10 pb-12 border-b border-white/10">
			<div class="col-span-2">
				<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="text-xl font-semibold tracking-tight text-white">InstaWP</a>
				<p class="mt-3 text-sm text-white/50 max-w-xs leading-relaxed">
					Instant WordPress sandboxes, staging sites, and hosting — built for developers who ship fast.
				</p>
				<div class="flex items-center gap-4 mt-6">
					<a href="https://twitter.com/jeremypry" class="text-white/40 hover:text-white transition-colors" aria-label="Twitter">
						<svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>
					</a>
					<a href="https://github.com/InstaWP" class="text-white/40 hover:text-white transition-colors" aria-label="GitHub">
						<svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path fill-rule="evenodd" clip-rule="evenodd" d="M12 2C6.477 2 2 6.484 2 12.017c0 4.425 2.865 8.18 6.839 9.504.5.092.682-.217.682-.483 0-.237-.008-.868-.013-1.703-2.782.605-3.369-1.343-3.369-1.343-.454-1.158-1.11-1.466-1.11-1.466-.908-.62.069-.608.069-.608 1.003.07 1.531 1.032 1.531 1.032.892 1.53 2.341 1.088 2.91.832.092-.647.35-1.088.636-1.338-2.22-.253-4.555-1.113-4.555-4.951 0-1.093.39-1.988 1.029-2.688-.103-.253-.446-1.272.098-2.65 0 0 .84-.27 2.75 1.026A9.564 9.564 0 0112 6.844c.85.004 1.705.115 2.504.337 1.909-1.296 2.747-1.027 2.747-1.027.546 1.379.202 2.398.1 2.651.64.7 1.028 1.595 1.028 2.688 0 3.848-2.339 4.695-4.566 4.943.359.309.678.92.678 1.855 0 1.338-.012 2.419-.012 2.747 0 .268.18.58.688.482A10.019 10.019 0 0022 12.017C22 6.484 17.522 2 12 2z"/></svg>
					</a>
					<a href="https://www.youtube.com/@InstaWP" class="text-white/40 hover:text-white transition-colors" aria-label="YouTube">
						<svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/></svg>
					</a>
				</div>
			</div>

			<?php foreach ( $footer_links as $heading => $links ) : ?>
				<div>
					<h4 class="text-sm font-semibold text-white/80 mb-4"><?php echo esc_html( $heading ); ?></h4>
					<ul class="space-y-2.5">
						<?php foreach ( $links as $link ) : ?>
							<li>
								<a href="<?php echo esc_url( $link['url'] ); ?>" class="text-sm text-white/40 hover:text-white transition-colors">
									<?php echo esc_html( $link['title'] ); ?>
								</a>
							</li>
						<?php endforeach; ?>
					</ul>
				</div>
			<?php endforeach; ?>
		</div>

		<div class="flex flex-col sm:flex-row items-center justify-between pt-8 gap-4">
			<p class="text-xs text-white/30">&copy; <?php echo date( 'Y' ); ?> InstaWP. All rights reserved.</p>
			<div class="flex items-center gap-6">
				<a href="/privacy/" class="text-xs text-white/30 hover:text-white/60 transition-colors">Privacy</a>
				<a href="/terms/" class="text-xs text-white/30 hover:text-white/60 transition-colors">Terms</a>
			</div>
		</div>
	</div>
</footer>
