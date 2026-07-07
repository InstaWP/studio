import { store, getContext } from '@wordpress/interactivity';

store( 'instawpNav', {
	state: {
		get isDropdownOpen_0() {
			return getContext().activeDropdown === '0';
		},
		get isDropdownOpen_1() {
			return getContext().activeDropdown === '1';
		},
		get isMobileOpen_0() {
			return getContext().mobileAccordion === '0';
		},
		get isMobileOpen_1() {
			return getContext().mobileAccordion === '1';
		},
	},
	actions: {
		toggle: () => {
			const context = getContext();
			context.isOpen = ! context.isOpen;
			// Reset accordion when closing
			if ( ! context.isOpen ) {
				context.mobileAccordion = '';
			}
		},
		openDropdown: ( event ) => {
			const context = getContext();
			const id = event.currentTarget.dataset.dropdownId;
			if ( id !== undefined ) {
				context.activeDropdown = id;
			}
		},
		closeDropdown: () => {
			const context = getContext();
			context.activeDropdown = '';
		},
		toggleDropdown: ( event ) => {
			const context = getContext();
			const id = event.currentTarget.dataset.dropdownId;
			if ( context.activeDropdown === id ) {
				context.activeDropdown = '';
			} else {
				context.activeDropdown = id;
			}
		},
		toggleMobileAccordion: ( event ) => {
			const context = getContext();
			const id = event.currentTarget.dataset.dropdownId;
			if ( context.mobileAccordion === id ) {
				context.mobileAccordion = '';
			} else {
				context.mobileAccordion = id;
			}
		},
	},
} );
