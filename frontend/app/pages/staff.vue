<script setup lang="ts">
import { PERMISSIONS } from '~/utils/permissions'

definePageMeta({ permissions: [PERMISSIONS.usersView, PERMISSIONS.shiftsView, PERMISSIONS.shiftsClock] })
</script>

<template>
  <StaffAccountDirectory directory="staff" />
</template>
