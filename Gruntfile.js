module.exports = function (grunt) {
    grunt.initConfig({
        pkg: grunt.file.readJSON("package.json"),
        name: 'minio-webhook',
        compress: {
            dist: {
                options: {
                    mode: 'zip',
                    archive: 'dist/<%= name %>.zip'
                },
                files: [{
                    src: [
                        'src/**',
                        'lib/**',
                        'inc/**',
                        'config/**',
                        'static/**',
                        'locale/**',
                        'templates/**',
                        '<%= name %>.php',
                        'README.md',
                        'webhook.php'
                    ], dest: '<%= name %>/'
                }]
            }
        },
    });
    grunt.loadNpmTasks("grunt-contrib-compress");
    grunt.registerTask("dist", ["compress:dist"]);
};