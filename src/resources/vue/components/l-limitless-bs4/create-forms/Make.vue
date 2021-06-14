<template>

    <!-- Main content -->
    <div class="content-wrapper">

        <on-invoice-form v-if="$route.query.invoice"></on-invoice-form>

        <general-form v-else></general-form>

    </div>
    <!-- /main content -->

</template>

<script>

    import GeneralForm from './General'
    import OnInvoiceForm from './OnInvoice'

    export default {
        components: {
            GeneralForm,
            OnInvoiceForm
        },
        mounted() {
            this.$root.appMenu('accounting')
        }
    }
</script>
