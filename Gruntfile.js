module.exports = function(grunt) {
    grunt.initConfig({
        shell: {
            generateClearpayZip: {
                command:
                    'cp module.zip clearpay-$(git rev-parse --abbrev-ref HEAD).zip \n'
            },
            generateAfterpayZip: {
                command:
                    'cp module.zip afterpay-$(git rev-parse --abbrev-ref HEAD).zip \n'
            },
            generateClearpayBrand: {
                command:
                'sed  \'26s/.*/    const DEFAULT_BRAND = "CP";/\' clearpay.php > clearpayCP.php \n' +
                'sed  \'22s/.*/    const PRODUCT_NAME = "Clearpay";/\' controllers/front/notify.php > controllers/front/notifyCP.php \n' +
                'mv clearpayCP.php clearpay.php \n' +
                'mv controllers/front/notifyCP.php controllers/front/notify.php \n'
            },
            generateAfterpayBrand: {
                command:
                'sed  \'26s/.*/    const DEFAULT_BRAND = "AP";/\' clearpay.php > clearpayAP.php \n' +
                'sed  \'22s/.*/    const PRODUCT_NAME = "Afterpay";/\' controllers/front/notify.php > controllers/front/notifyAP.php \n' +
                'mv clearpayAP.php clearpay.php \n' +
                'mv controllers/front/notifyAP.php controllers/front/notify.php \n'
            },
            autoindex: {
                command:
                    'composer global require pagantis/autoindex \n' +
                    'php ~/.composer/vendor/pagantis/autoindex/index.php ./ || true \n' +
                    'php /home/circleci/.config/composer/vendor/pagantis/autoindex/index.php . || true \n'

            },
            composerProd: {
                command: 'composer install --no-dev'
            },
            composerDev: {
                command: 'composer install --ignore-platform-reqs'
            },
            runTestPrestashop17: {
                command:
                    'docker-compose down\n' +
                    'docker-compose up -d selenium\n' +
                    'docker-compose up -d prestashop17-test\n' +
                    'echo "Creating the prestashop17-test"\n' +
                    'sleep 100\n' +
                    'date\n' +
                    'docker-compose logs prestashop17-test\n' +
                    'set -e\n' +
                    'vendor/bin/phpunit --group prestashop17basic\n'
            },
            runTestPrestashop16: {
                command:
                    'docker-compose down\n' +
                    'docker-compose up -d selenium\n' +
                    'docker-compose up -d prestashop16-test\n' +
                    'echo "Creating the prestashop16-test"\n' +
                    'sleep  90\n' +
                    'date\n' +
                    'docker-compose logs prestashop16-test\n' +
                    'set -e\n' +
                    'vendor/bin/phpunit --group prestashop16basic\n'
            },
        },
        compress: {
            main: {
                options: {
                    archive: 'module.zip'
                },
                files: [
                    {src: ['controllers/**'], dest: 'clearpay/', filter: 'isFile'},
                    {src: ['classes/**'], dest: 'clearpay/', filter: 'isFile'},
                    {src: ['docs/**'], dest: 'clearpay/', filter: 'isFile'},
                    {src: ['override/**'], dest: 'clearpay/', filter: 'isFile'},
                    {src: ['logs/**'], dest: 'clearpay/', filter: 'isFile'},
                    {src: ['vendor/**'], dest: 'clearpay/', filter: 'isFile'},
                    {src: ['translations/**'], dest: 'clearpay/', filter: 'isFile'},
                    {src: ['upgrade/**'], dest: 'clearpay/', filter: 'isFile'},
                    {src: ['optionaloverride/**'], dest: 'clearpay/', filter: 'isFile'},
                    {src: ['oldoverride/**'], dest: 'clearpay/', filter: 'isFile'},
                    {src: ['sql/**'], dest: 'clearpay/', filter: 'isFile'},
                    {src: ['lib/**'], dest: 'clearpay/', filter: 'isFile'},
                    {src: ['defaultoverride/**'], dest: 'clearpay/', filter: 'isFile'},
                    {src: ['views/**'], dest: 'clearpay/', filter: 'isFile'},
                    {src: '.htaccess', dest: 'clearpay/'},
                    {src: 'index.php', dest: 'clearpay/'},
                    {src: 'clearpay.php', dest: 'clearpay/'},
                    {src: 'logo.png', dest: 'clearpay/'},
                    {src: 'README.md', dest: 'clearpay/'}
                ]
            }
        }
    });

    grunt.loadNpmTasks('grunt-shell');
    grunt.loadNpmTasks('grunt-contrib-compress');
    grunt.registerTask('default', [
        'shell:composerProd',
        'shell:autoindex',
        'shell:generateAfterpayBrand',
        'compress',
        'shell:generateAfterpayZip',
        'shell:generateClearpayBrand',
        'compress',
        'shell:generateClearpayZip',
        'shell:composerDev'
    ]);

    //manually run the selenium test: "grunt shell:testPrestashop16"
};