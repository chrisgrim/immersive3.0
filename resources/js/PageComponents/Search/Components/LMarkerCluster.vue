<template>
    <slot />
  </template>

<script setup>
import { onMounted, onBeforeUnmount, provide, inject, getCurrentInstance } from 'vue'
import L from 'leaflet'
import 'leaflet.markercluster'

const props = defineProps({
  options: {
    type: Object,
    default: () => ({})
  }
})

const markerClusterGroup = L.markerClusterGroup(props.options)

onMounted(() => {
  const instance = getCurrentInstance()
  const mapRef = instance?.parent?.refs?.map
  const leafletMap = mapRef?._value

  if (leafletMap) {
    leafletMap.addLayer(markerClusterGroup)
  }
})

onBeforeUnmount(() => {
  markerClusterGroup.clearLayers()
  const instance = getCurrentInstance()
  const mapRef = instance?.parent?.refs?.map
  const leafletMap = mapRef?._value
  
  if (leafletMap) {
    leafletMap.removeLayer(markerClusterGroup)
  }
})

provide('markerClusterGroup', markerClusterGroup)
</script>
