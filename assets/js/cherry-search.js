(function( $, CherryJsCore ) {
	'use strict';

	CherryJsCore.utilites.namespace( 'cherrySearch' );
	CherryJsCore.cherrySearch = {
		settings: {
			searchFormWrapperClass: '.cherry-search-wrapper',
			searchFormClass: '.cherry-search__form',
			inputClass: '.cherry-search__field',
			submitClass: '.cherry-search__submit',
			resultsAreaClass: '.cherry-search__results-area',
			listHolderClass: '.cherry-search__results-holder',
			listClass: '.cherry-search__results-list',
			listInnerClass: '.cherry-search__results-list-inner',
			itemClass: '.cherry-search__results-item',
			messageHolder: '.cherry-search__message',
			countClass: '.cherry-search__results-count span',

			navigationClass: '.cherry-search__navigation-holder',
			bulletClass: '.cherry-search__bullet-button',
			bulletActiveClass: '.cherry-search__active-button',
			numberClass: '.cherry-search__number-button',
			prevClass: '.cherry-search__prev-button',
			nextClass: '.cherry-search__next-button',

			spinner: '.cherry-search__spinner',
			fullResult: '.cherry-search__full-result',
			searchHandlerId: 'cherry_search_public_action'
		},

		init: function() {
			$( 'body' ).on( 'focus' + this.settings.searchFormWrapperClass, this.settings.inputClass, this.initCherrySearch.bind( this ) );
		},

		initCherrySearch: function( event ) {
			var search = $( event.target ).closest( this.settings.searchFormWrapperClass );

			search.cherrySearch( this.settings );
		}
	};

	CherryJsCore.cherrySearch.init();

	$.fn.cherrySearch = function( args ) {
		var self = this[0],
			settings          = args,
			messages          = window.cherrySearchMessages,
			timer             = null,
			itemTemplate      = null,
			resultsArea       = $( settings.resultsAreaClass, self ),
			resultsHolder     = $( settings.listHolderClass, resultsArea ),
			countHolder       = $( settings.countClass, resultsArea ),
			resultsList       = $( settings.listClass, resultsArea ),
			resultsListInner  = $( settings.listInnerClass, resultsArea ),
			resultsNavigation = $( settings.navigationClass, resultsArea ),
			messageHolder     = $( settings.messageHolder, resultsArea ),
			spinner           = $( settings.spinner, resultsArea ),
			data              = $( self ).data( 'args' ) || [],
			currentPosition = 0;

		if ( ! self.isInit ) {
			self.isInit       = true;

			self.inputChangeHandler = function( event ) {
				var value = event.target.value;

				resultsHolder.removeClass( 'show' );
				self.outputMessage( '', '' );

				if ( value ) {
					self.showList();
					spinner.addClass( 'show' );

					clearTimeout( timer );
					timer = setTimeout( function() {
						data.value = value;
						self.searchAjaxInstancer.sendData( data );
					}, 450 );
				} else {
					self.hideList();
				}
			};

			self.successCallback = function( response ) {
				var date       = response.data,
					error      = date.error,
					message    = date.message,
					posts      = date.posts,
					post       = null,
					outputHtml = '',
					listItemHtml = '',
					listHtml = '<ul>%s</ul>';

				resultsHolder.removeClass( 'show' );
				spinner.removeClass( 'show' );
				currentPosition = 0;

				if ( 'error-notice' !== response.type ) {
					if ( 0 === date.post_count || error ) {
						self.outputMessage( message, 'show' );
					} else {
						messageHolder.removeClass( 'show' );
						itemTemplate = wp.template( 'search-form-results-item-' + data.id );

						for ( post in posts ) {
							listItemHtml += itemTemplate( posts[ post ] );
							if( ( parseInt( post ) + 1 ) % date.limit_query == 0
								|| parseInt( post ) === posts.length - 1 ) {
								outputHtml += listHtml.replace( '%s', listItemHtml );
								listItemHtml = '';
							}
						}

						countHolder.html( date.post_count );
						resultsListInner
							.html( outputHtml )
							.attr( 'data-columns', date.columns );
						resultsNavigation.html( date.result_navigation );
						resultsHolder.addClass( 'show' );

					}
				} else {
					self.outputMessage( messages.serverError, 'error show' );
				}
			};

			self.errorCallback = function( jqXHR ) {
				if ( 'abort' !== jqXHR.statusText ) {
					spinner.removeClass( 'show' );
					self.outputMessage( messages.serverError, 'error show' );
				}
			};

			self.hideList = function() {
				resultsArea.removeClass( 'show' );
			};

			self.showList = function() {
				resultsArea.addClass( 'show' );
			};

			self.focusHandler = function() {
				if ( 0 !== $( 'ul > li', resultsListInner ).length ) {
					self.showList();
				}
			};

			self.outputMessage = function( message, messageClass ) {
				messageHolder.removeClass( 'error show' ).addClass( messageClass ).html( message );
			};

			self.formClick = function( event ) {
				event.stopPropagation();
			};

			self.clickFullResult = function() {
				$( settings.searchFormClass, self ).submit();
			};

			self.changeSlide = function( number ) {
				var position = parseInt( $( 'ul', resultsListInner ).eq( number ).position().left ) * -1 ;

				resultsListInner.css( 'left', position + 'px' );
				resultsList.scrollTop(0);
			};

			self.clickBulletHandler = function( event ) {
				var target = $( event.target ),
					activeClass = settings.bulletActiveClass.replace( '.', '' );

				$( settings.bulletActiveClass ).removeClass( activeClass );
				target.addClass( activeClass );

				self.changeSlide( target.data( 'number' ) - 1 );
			};

			self.clickNavigationButtonHandler = function( event ) {
				var direction    = $( event.target ).data( 'direction' ),
					lastPosition = resultsListInner.data( 'columns' );

				if ( 0 <= currentPosition + direction && lastPosition > currentPosition + direction ) {
					currentPosition = currentPosition + direction;
					self.changeSlide( currentPosition );
				}

			};

			self.searchAjaxInstancer = new CherryJsCore.CherryAjaxHandler( {
				handlerId: settings.searchHandlerId,
				successCallback: self.successCallback,
				errorCallback: self.errorCallback
			} );

			$( settings.inputClass, self )
				.on( 'input', self.inputChangeHandler )
				.on( 'focus', self.focusHandler )
				/*.on( 'blur', self.hideList )*/;

			$( self )
				.on( 'click' + settings.searchFormWrapperClass, self.formClick )
				.on( 'click' + settings.searchFormWrapperClass, settings.fullResult, self.clickFullResult )
				.on( 'click' + settings.searchFormWrapperClass, settings.bulletClass, self.clickBulletHandler )
				.on( 'click' + settings.searchFormWrapperClass, settings.numberClass, self.clickBulletHandler )
				.on( 'click' + settings.searchFormWrapperClass, settings.prevClass, self.clickNavigationButtonHandler )
				.on( 'click' + settings.searchFormWrapperClass, settings.nextClass, self.clickNavigationButtonHandler );

			$( 'body' )
				.on( 'click' + settings.searchFormWrapperClass, self.hideList );

		} else {
			return 'is init: true';
		}
	};
}( jQuery, window.CherryJsCore ) );
