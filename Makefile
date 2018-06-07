default: assets

_PHONY: assets

assets: \
		public/assets/vendor/zepto.js \
		public/assets/vendor/underscore.js \
		public/assets/vendor/backbone.js

public/assets/vendor/zepto.js: public/assets/vendor
	curl -o public/assets/vendor/zepto.js \
		'https://cdnjs.cloudflare.com/ajax/libs/zepto/1.2.0/zepto.js'

public/assets/vendor/underscore.js:
	curl -o public/assets/vendor/underscore.js \
		'https://cdnjs.cloudflare.com/ajax/libs/underscore.js/1.9.1/underscore.js'

public/assets/vendor/backbone.js:
	curl -o public/assets/vendor/backbone.js \
		'https://cdnjs.cloudflare.com/ajax/libs/backbone.js/1.3.3/backbone.js'

# vim: set noet ts=4 sts=4 sw=4:
