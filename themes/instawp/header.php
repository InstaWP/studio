<?php
/**
 * Theme header.
 *
 * Two variants:
 *  - Static-design pages (home, pricing) → static-design header.
 *  - All other pages → classic header (white bg, dropdown nav).
 */

if ( function_exists( 'instawp_use_static_design' ) && instawp_use_static_design() ) :
	?>
	<header class="header" id="header">
		<div class="container header-inner">
			<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="logo" aria-label="InstaWP home">
				<div class="logo-icon"></div>
				InstaWP
			</a>

			<nav>
				<ul class="nav-links">
					<li class="nav-dropdown">
						<a href="<?php echo esc_url( home_url( '/sandbox/' ) ); ?>" class="nav-dropdown-toggle">Platform <svg viewBox="0 0 12 12" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 5l3 3 3-3"/></svg></a>
						<div class="nav-dropdown-menu">
							<span class="dropdown-section">Build</span>
							<a href="<?php echo esc_url( home_url( '/sandbox/' ) ); ?>">Sandbox</a>
							<a href="#"><span>Templates</span></a>
							<a href="#"><span>Snapshots</span></a>
							<span class="dropdown-section">Stage &amp; Ship</span>
							<a href="<?php echo esc_url( home_url( '/hosting/' ) ); ?>">Hosting</a>
							<a href="<?php echo esc_url( home_url( '/migrate/' ) ); ?>">Migrations</a>
							<a href="#"><span>Two-Way Sync</span></a>
							<span class="dropdown-section">Advanced</span>
							<a href="#"><span>CLI &amp; API</span></a>
							<a href="#"><span>Site Management</span></a>
						</div>
					</li>
					<li class="nav-dropdown">
						<a href="<?php echo esc_url( home_url( '/for-agencies/' ) ); ?>" class="nav-dropdown-toggle">Solutions <svg viewBox="0 0 12 12" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 5l3 3 3-3"/></svg></a>
						<div class="nav-dropdown-menu">
							<span class="dropdown-section">Use cases</span>
							<a href="<?php echo esc_url( home_url( '/migrate/' ) ); ?>">Migrate WordPress</a>
							<a href="#"><span>Two-way sync</span></a>
							<a href="#"><span>White-label (WaaS)</span></a>
							<a href="#"><span>Plugin demos</span></a>
							<a href="#"><span>Multi-site management</span></a>
							<span class="dropdown-section">For your team</span>
							<a href="<?php echo esc_url( home_url( '/for-agencies/' ) ); ?>">For Agencies</a>
							<a href="#"><span>For Developers</span></a>
							<a href="#"><span>For Plugin Authors</span></a>
							<a href="#"><span>For Educators</span></a>
						</div>
					</li>
					<li><a href="<?php echo esc_url( home_url( '/pricing/' ) ); ?>">Pricing</a></li>
					<li><a href="#">Docs</a></li>
					<li><a href="<?php echo esc_url( home_url( '/about/' ) ); ?>">About</a></li>
				</ul>
			</nav>

			<div class="header-actions">
				<a href="https://app.instawp.io/login" class="login">Log in</a>
				<a href="https://app.instawp.io/onboard" class="btn btn-primary btn-sm">Create a Free Site</a>
			</div>

			<button class="mobile-toggle" id="mobileToggle" aria-label="Toggle menu">
				<span></span><span></span><span></span>
			</button>
		</div>

		<div class="mobile-nav" id="mobileNav">
			<a href="<?php echo esc_url( home_url( '/sandbox/' ) ); ?>">Features</a>
			<a href="<?php echo esc_url( home_url( '/for-agencies/' ) ); ?>">Solutions</a>
			<a href="<?php echo esc_url( home_url( '/pricing/' ) ); ?>">Pricing</a>
			<a href="#">Docs</a>
			<a href="<?php echo esc_url( home_url( '/about/' ) ); ?>">About</a>
			<a href="https://app.instawp.io/login">Log in</a>
			<a href="https://app.instawp.io/onboard" class="btn btn-primary">Create a Free Site</a>
		</div>
	</header>
	<?php
	return;
endif;

// Classic header for non-front pages.
$nav_items = array(
	array(
		'title'    => 'Platform',
		'url'      => '/features/',
		'dropdown' => array(
			array( 'title' => 'Sites & Sandboxes', 'desc' => 'Spin up WordPress in seconds', 'url' => '/features/sites/' ),
			array( 'title' => 'Snapshots', 'desc' => 'Save and restore site states', 'url' => '/features/snapshots/' ),
			array( 'title' => 'Migrations', 'desc' => 'Move sites between any host', 'url' => '/features/migrations/' ),
			array( 'title' => 'Templates', 'desc' => 'Reusable WordPress configs', 'url' => '/features/' ),
			array( 'title' => 'Site Management', 'desc' => 'Update and manage at scale', 'url' => '/features/' ),
			array( 'title' => 'All Features', 'desc' => 'See everything InstaWP offers', 'url' => '/features/' ),
		),
	),
	array(
		'title'    => 'Solutions',
		'url'      => '/solutions/',
		'dropdown' => array(
			array( 'title' => 'Agencies', 'desc' => 'Client builds and handoffs', 'url' => '/solutions/' ),
			array( 'title' => 'Developers', 'desc' => 'Dev environments and testing', 'url' => '/solutions/' ),
			array( 'title' => 'Educators', 'desc' => 'Classroom sandboxes', 'url' => '/solutions/' ),
			array( 'title' => 'Plugin Authors', 'desc' => 'Test across WP versions', 'url' => '/solutions/' ),
		),
	),
	array( 'title' => 'Pricing', 'url' => '/pricing/' ),
	array( 'title' => 'Resources', 'url' => '/blog/' ),
);
?>
<header class="fixed top-0 left-0 right-0 z-[999] bg-white/80 backdrop-blur-md border-b border-gray-100"
        data-wp-interactive="instawpNav"
        data-wp-context='{ "isOpen": false, "activeDropdown": "" }'>
	<div class="flex items-center justify-between max-w-7xl mx-auto px-6 py-4">
		<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="font-semibold text-lg tracking-tight text-[var(--color-dark)]" aria-label="InstaWP Home">
			InstaWP
		</a>

		<nav class="hidden md:flex items-center gap-8" aria-label="Main navigation">
			<?php foreach ( $nav_items as $i => $item ) : ?>
				<?php if ( ! empty( $item['dropdown'] ) ) : ?>
					<div class="relative"
					     data-wp-on--mouseenter="actions.openDropdown"
					     data-wp-on--mouseleave="actions.closeDropdown"
					     data-dropdown-id="<?php echo esc_attr( $i ); ?>">
						<button class="inline-flex items-center gap-1 text-sm font-medium text-gray-600 hover:text-gray-900 transition-colors"
						        data-wp-on--click="actions.toggleDropdown"
						        data-dropdown-id="<?php echo esc_attr( $i ); ?>">
							<?php echo esc_html( $item['title'] ); ?>
							<svg class="w-3.5 h-3.5 transition-transform" data-wp-class--rotate-180="state.isDropdownOpen_<?php echo esc_attr( $i ); ?>" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5"/></svg>
						</button>
						<div class="absolute top-full left-1/2 -translate-x-1/2 pt-3"
						     data-wp-bind--hidden="!state.isDropdownOpen_<?php echo esc_attr( $i ); ?>"
						     hidden>
							<div class="bg-white rounded-xl shadow-xl border border-gray-100 p-3 min-w-[280px] nav-dropdown">
								<?php foreach ( $item['dropdown'] as $link ) : ?>
									<a href="<?php echo esc_url( $link['url'] ); ?>" class="flex flex-col gap-0.5 px-4 py-3 rounded-lg hover:bg-gray-50 transition-colors">
										<span class="text-sm font-medium text-[var(--color-heading)]"><?php echo esc_html( $link['title'] ); ?></span>
										<span class="text-xs text-[var(--color-muted)]"><?php echo esc_html( $link['desc'] ); ?></span>
									</a>
								<?php endforeach; ?>
							</div>
						</div>
					</div>
				<?php else : ?>
					<a href="<?php echo esc_url( $item['url'] ); ?>" class="text-sm font-medium text-gray-600 hover:text-gray-900 transition-colors">
						<?php echo esc_html( $item['title'] ); ?>
					</a>
				<?php endif; ?>
			<?php endforeach; ?>
		</nav>

		<div class="flex items-center gap-3">
			<a href="https://app.instawp.io/login" class="text-sm font-medium text-gray-600 hover:text-gray-900 transition-colors hidden md:inline-flex">Log In</a>
			<a href="https://app.instawp.io/onboard" class="rounded-lg bg-[var(--color-primary)] text-white px-5 py-2.5 text-sm font-semibold hover:bg-[var(--color-primary-dark)] transition-colors hidden sm:inline-flex">Get Started</a>

			<button class="md:hidden relative w-10 h-10 flex items-center justify-center rounded-lg hover:bg-gray-100 transition-colors"
			        data-wp-on--click="actions.toggle"
			        data-wp-bind--aria-expanded="context.isOpen"
			        aria-label="Toggle menu">
				<svg data-wp-bind--hidden="context.isOpen" class="w-5 h-5 text-gray-700" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
					<path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5"/>
				</svg>
				<svg data-wp-bind--hidden="!context.isOpen" class="w-5 h-5 text-gray-700" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true" hidden>
					<path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
				</svg>
			</button>
		</div>
	</div>

	<div class="md:hidden border-t border-gray-100 bg-white"
	     data-wp-bind--hidden="!context.isOpen">
		<nav class="px-6 py-4 space-y-1" aria-label="Mobile navigation">
			<?php foreach ( $nav_items as $i => $item ) : ?>
				<?php if ( ! empty( $item['dropdown'] ) ) : ?>
					<div class="border-b border-gray-50 last:border-0">
						<button class="flex items-center justify-between w-full py-3 text-base font-medium text-gray-700 hover:text-gray-900 transition-colors"
						        data-wp-on--click="actions.toggleMobileAccordion"
						        data-dropdown-id="<?php echo esc_attr( $i ); ?>">
							<?php echo esc_html( $item['title'] ); ?>
							<svg class="w-4 h-4 text-gray-400 transition-transform" data-wp-class--rotate-180="state.isMobileOpen_<?php echo esc_attr( $i ); ?>" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5"/></svg>
						</button>
						<div class="overflow-hidden" data-wp-bind--hidden="!state.isMobileOpen_<?php echo esc_attr( $i ); ?>" hidden>
							<div class="pb-3 pl-4 space-y-1">
								<?php foreach ( $item['dropdown'] as $link ) : ?>
									<a href="<?php echo esc_url( $link['url'] ); ?>" class="block py-2 text-sm text-gray-600 hover:text-gray-900 transition-colors">
										<?php echo esc_html( $link['title'] ); ?>
									</a>
								<?php endforeach; ?>
							</div>
						</div>
					</div>
				<?php else : ?>
					<a href="<?php echo esc_url( $item['url'] ); ?>" class="block py-3 text-base font-medium text-gray-700 hover:text-gray-900 transition-colors border-b border-gray-50 last:border-0">
						<?php echo esc_html( $item['title'] ); ?>
					</a>
				<?php endif; ?>
			<?php endforeach; ?>
			<div class="pt-4 pb-2 flex flex-col gap-3">
				<a href="https://app.instawp.io/login" class="text-center py-3 text-base font-medium text-gray-700 hover:text-gray-900 transition-colors">Log In</a>
				<a href="https://app.instawp.io/onboard" class="btn-primary text-center">Get Started Free</a>
			</div>
		</nav>
	</div>
</header>
