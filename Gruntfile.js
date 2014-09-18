module.exports = function(grunt) {

    // Project configuration.
    grunt.initConfig({
        pkg: grunt.file.readJSON('package.json'),
        clean: {
            all: ['public/packages/*']
        },
        watch: {
            styles: {
                files: 'vendor/fluxbb/core/public/**/*',
                tasks: ['clean', 'shell:publish']
            }
        },
        shell: {
            publish: {
                command: 'php artisan publish:assets fluxbb/core'
            }
        }
    });

    grunt.loadNpmTasks('grunt-contrib-clean');
    grunt.loadNpmTasks('grunt-contrib-watch');
    grunt.loadNpmTasks('grunt-shell');

    // Default task(s).
    grunt.registerTask('default', ['clean', 'shell:publish', 'watch']);

};
