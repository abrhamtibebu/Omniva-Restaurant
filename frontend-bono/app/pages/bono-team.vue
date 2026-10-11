<script setup lang="ts">
import { PERMISSIONS } from '~/utils/permissions'

definePageMeta({
  permissions: [PERMISSIONS.usersView, PERMISSIONS.usersManage],
})
</script>

<template>
  <StaffAccountDirectory directory="bono" />
</template>
