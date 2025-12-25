import template from './wsc-variant-updater-index.html.twig';

const { Component, Mixin } = Shopware;
const { Criteria } = Shopware.Data;

Component.register('wsc-variant-updater-index', {
    template,

    inject: ['repositoryFactory', 'systemConfigApiService', 'context'],

    mixins: [
        Mixin.getByName('notification')
    ],

    data() {
        return {
            isLoading: false,
            mode: 'product-numbers', // 'all', 'product-numbers', 'entity-select'
            productNumbers: '',
            selectedProducts: [],
            showAllConfirmation: false,
            config: {
                dryRun: false
            },
            progress: null,
            progressInterval: null
        };
    },

    computed: {
        productRepository() {
            return this.repositoryFactory.create('product');
        },

        productCriteria() {
            const criteria = new Criteria();
            criteria.addFilter(Criteria.equals('parentId', null));
            return criteria;
        },

        modeOptions() {
            return [
                {
                    value: 'product-numbers',
                    label: this.$tc('wsc-variant-updater.index.modeProductNumbers')
                },
                {
                    value: 'all',
                    label: this.$tc('wsc-variant-updater.index.modeAll')
                }
            ];
        }
    },

    created() {
        this.loadConfig();
    },

    beforeDestroy() {
        if (this.progressInterval) {
            clearInterval(this.progressInterval);
        }
    },

    methods: {
        async loadConfig() {
            // Keine Config mehr laden - nameOnly/numberOnly kommen aus SystemConfig
            // und werden vom Backend automatisch verwendet
        },

        onModeChange() {
            this.productNumbers = '';
            this.selectedProducts = [];
            this.showAllConfirmation = false;
        },


        onExecute() {
            if (this.mode === 'all') {
                this.showAllConfirmation = true;
            } else {
                this.executeUpdate();
            }
        },

        onConfirmAll() {
            this.showAllConfirmation = false;
            this.executeUpdate();
        },

        onCancelAll() {
            this.showAllConfirmation = false;
        },

        async executeUpdate() {
            this.isLoading = true;

            try {
                let productNumbers = [];

                if (this.mode === 'all') {
                    productNumbers = await this.getAllProductNumbers();
                } else if (this.mode === 'product-numbers') {
                    productNumbers = this.productNumbers.split(',').map(n => n.trim()).filter(n => n);
                }

                if (productNumbers.length === 0) {
                    this.createNotificationWarning({
                        message: this.$tc('wsc-variant-updater.notification.noProductsSelected')
                    });
                    this.isLoading = false;
                    return;
                }

                const httpClient = Shopware.Application.getContainer('init').httpClient;
                const response = await httpClient.post(
                    '/_action/wsc-variant-updater/update',
                    {
                        productNumbers: productNumbers,
                        dryRun: this.config.dryRun
                        // nameOnly und numberOnly kommen automatisch aus SystemConfig
                    }
                );

                const data = response.data;

                if (data.success) {
                    this.createNotificationSuccess({
                        message: this.$tc('wsc-variant-updater.notification.updateQueued', 0, {
                            count: data.productCount
                        })
                    });

                    // Start progress tracking
                    this.startProgressTracking(data.batchId);
                } else {
                    this.createNotificationError({
                        message: data.error || this.$tc('wsc-variant-updater.notification.updateFailed')
                    });
                }
            } catch (error) {
                this.createNotificationError({
                    message: error.message || this.$tc('wsc-variant-updater.notification.updateFailed')
                });
            } finally {
                this.isLoading = false;
            }
        },

        async getAllProductNumbers() {
            const products = await this.productRepository.search(this.productCriteria);
            return products.map(p => p.productNumber);
        },

        startProgressTracking(batchId) {
            this.progress = {
                batchId: batchId,
                percentage: 0,
                status: 'pending'
            };

            this.progressInterval = setInterval(() => {
                this.updateProgress(batchId);
            }, 2000);
        },

        async updateProgress(batchId) {
            try {
                const httpClient = Shopware.Application.getContainer('init').httpClient;
                const response = await httpClient.get(
                    `/_action/wsc-variant-updater/progress/${batchId}`
                );
                const data = response.data;

                if (data.success) {
                    this.progress = {
                        ...this.progress,
                        ...data,
                        percentage: data.percentage
                    };

                    if (data.status === 'completed' || data.status === 'failed') {
                        clearInterval(this.progressInterval);
                        this.progressInterval = null;

                        if (data.status === 'completed') {
                            this.createNotificationSuccess({
                                message: this.$tc('wsc-variant-updater.notification.updateCompleted', 0, {
                                    count: data.processedProducts
                                })
                            });
                        } else {
                            this.createNotificationError({
                                message: this.$tc('wsc-variant-updater.notification.updateFailedWithErrors')
                            });
                        }
                    }
                }
            } catch (error) {
                console.error('Failed to get progress:', error);
            }
        }
    }
});
