<template>

    <panel-item :field="field">

        <file-pond-wrapper
            slot="value"
            :disabled="true"
            :allow-image-preview="true"
            :limit="field.value.length"
            :allow-remove="false"
            :field="field"/>

    </panel-item>

</template>

<script>

    import FilePondWrapper from './FilePondWrapper'

    export default {
        components: {FilePondWrapper},
        props: ['resource', 'resourceName', 'resourceId', 'field']
    }

</script>