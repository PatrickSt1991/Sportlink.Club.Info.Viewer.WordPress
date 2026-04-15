<template>
  <div class="scv-no-config">
    <div class="scv-no-config-inner">
      <div class="scv-no-config-icon">⚙️</div>
      <h2>Sportlink Club Viewer</h2>
      <p>De plugin is nog niet geconfigureerd.</p>
      <p class="scv-no-config-hint">
        Ga naar <strong>Instellingen → Sportlink Viewer</strong> in het WordPress-beheergedeelte en stel je sport, verbindingstype en clubgegevens in.
      </p>
    </div>
  </div>
</template>

<style scoped>
.scv-no-config {
  display: flex;
  align-items: center;
  justify-content: center;
  min-height: 300px;
  padding: 40px 20px;
}

.scv-no-config-inner {
  background: rgba(255,255,255,0.92);
  color: #1e293b;
  border-radius: 8px;
  padding: 40px;
  max-width: 480px;
  text-align: center;
  box-shadow: 0 4px 24px rgba(0,0,0,0.18);
}

.scv-no-config-icon {
  font-size: 48px;
  margin-bottom: 16px;
}

.scv-no-config-inner h2 {
  margin: 0 0 12px;
  font-size: 1.4em;
  font-weight: 700;
}

.scv-no-config-hint {
  font-size: 0.9em;
  color: #475569;
  margin-top: 12px;
}
</style>
