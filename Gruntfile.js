module.exports = function( grunt ) { //The wrapper function

	require( 'load-grunt-tasks' )( grunt );

	// Project configuration & task configuration
	grunt.initConfig( {
		pkg: grunt.file.readJSON( 'package.json' ),

		// The uglify task and its configurations
		uglify: {
			options: {
				banner: '/*! <%= pkg.name %> <%= grunt.template.today("yyyy-mm-dd") %> */\n'
			},
			build: {
				files: [ {
					expand: true,     // Enable dynamic expansion.
					src: [ 'js/*.js', '!js/*.min.js' ], // Actual pattern(s) to match.
					ext: '.min.js'   // Dest filepaths will have this extension.
				} ]
			}
		},

		// The jshint task and its configurations
		jshint: {
			all: [ 'js/*.js', '!js/*.min.js' ]
		},

		wp_readme_to_markdown: {
			convert:{
				files: {
					'README.md': 'readme.txt'
				},
				options: {
					'screenshot_url': 'https://ps.w.org/{plugin}/assets/{screenshot}.png',
					'post_convert': function ( readme ) {
						readme = '[![Build Status](https://travis-ci.org/gitlost/unfc-normalize.png?branch=master)](https://travis-ci.org/gitlost/unfc-normalize)\n' + readme;
						return readme;
					}
				}
			}
		},

		makepot: {
			target: {
				options: {
					cwd: '',                          // Directory of files to internationalize.
					domainPath: '/languages',         // Where to save the POT file.
					exclude: [ 'perf/', 'tests/', 'class-unfc-list-table.php' ],   // List of files or directories to ignore.
					include: [],                      // List of files or directories to include.
					mainFile: 'unfc-normalize.php',     // Main project file.
					potComments: '',                  // The copyright at the beginning of the POT file.
					potFilename: '',                  // Name of the POT file.
					potHeaders: {
						poedit: true,                 // Includes common Poedit headers.
						'x-poedit-keywordslist': true // Include a list of all possible gettext functions.
					},                                // Headers to add to the generated POT file.
					processPot: null,                 // A callback function for manipulating the POT file.
					type: 'wp-plugin',                // Type of project (wp-plugin or wp-theme).
					updateTimestamp: true,            // Whether the POT-Creation-Date should be updated without other changes.
					updatePoFiles: false              // Whether to update PO files in the same directory as the POT file.
				}
			}
		},

		po2mo: {
			files: {
				src: 'languages/*.po',
				expand: true,
			},
		},

		phpunit: {
			classes: {
				dir: 'tests/'
			},
			options: {
				bin: 'PHPRC=. WP_TESTS_DIR=/var/www/wordpress-develop/tests/phpunit phpunit',
				configuration: 'phpunit.xml'
			}
		},

		clean: {
			js: [ 'js/*.min.js' ]
		}

	} );

	// Default task(s), executed when you run 'grunt'
	grunt.registerTask( 'default', [ 'uglify', 'wp_readme_to_markdown', 'makepot' ] );

	// Creating a custom task
	grunt.registerTask( 'test', [ 'jshint', 'phpunit' ] );

	grunt.registerTask( 'test_build', [ 'clean', 'uglify' ] );
};
