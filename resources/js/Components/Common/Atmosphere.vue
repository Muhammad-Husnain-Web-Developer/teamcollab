<template>
  <div class="app-atmosphere" aria-hidden="true">
    <div
      class="aurora-blob"
      style="width:560px;height:560px;background:radial-gradient(circle,#5c7cfa,transparent 70%);top:-180px;left:-120px;"
    />
    <div
      class="aurora-blob"
      style="width:420px;height:420px;background:radial-gradient(circle,#8b5cf6,transparent 70%);bottom:-140px;left:22%;animation-delay:-8s;animation-duration:30s;"
    />
    <div
      class="aurora-blob"
      style="width:340px;height:340px;background:radial-gradient(circle,#2dd4bf,transparent 70%);top:35%;right:8%;animation-delay:-4s;animation-duration:22s;"
    />
  </div>
</template>

<script setup>
// Shared ambient background — same aurora mesh + grain texture behind every
// shell (auth, workspace picker, app) so the "refined depth" look reads as
// one system instead of a one-off login-page effect.
</script>
