<template>
    <slot />
  </template>

<script setup>
import { onMounted, onBeforeUnmount, provide, inject, getCurrentInstance, watch } from 'vue'
import L from 'leaflet'
import 'leaflet.markercluster'

const props = defineProps({
  options: {
    type: Object,
    default: () => ({})
  }
})

const markerClusterGroup = L.markerClusterGroup(props.options)

const getLeafletMap = () => {
  const instance = getCurrentInstance()
  const mapRef = instance?.parent?.refs?.map
  return mapRef?.leafletObject || mapRef?._value
}

onMounted(() => {
  const leafletMap = getLeafletMap()
  if (leafletMap) {
    leafletMap.addLayer(markerClusterGroup)
  }

  // Watch for markers in parent and add them to cluster
  const instance = getCurrentInstance()
  if (instance?.parent?.slots?.default) {
    const markers = instance.parent.slots.default()
    markers.forEach(marker => {
      if (marker.component?.exposed?.leafletObject) {
        markerClusterGroup.addLayer(marker.component.exposed.leafletObject)
      }
    })
  }
})

onBeforeUnmount(() => {
  markerClusterGroup.clearLayers()
  const leafletMap = getLeafletMap()
  if (leafletMap) {
    leafletMap.removeLayer(markerClusterGroup)
  }
})

provide('markerClusterGroup', markerClusterGroup)
</script>
