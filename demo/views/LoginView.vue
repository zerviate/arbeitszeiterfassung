<script setup lang="ts">
import { ref } from 'vue'
import { demo } from '../store'
import { navigate } from '../router'
import { showToast } from '../toast'

const email = ref('test@example.com')
const password = ref('password')
const remember = ref(true)

function login(): void {
    if (email.value !== 'test@example.com' || password.value !== 'password') {
        showToast('Die Anmeldedaten sind ungueltig.', 'danger', 'Anmeldung fehlgeschlagen')
        return
    }
    demo.authenticated = true
    navigate('/time')
    showToast('Anmeldung erfolgreich.')
}
</script>

<template>
    <div class="guest-shell">
        <main class="guest-main">
            <div class="card" style="max-width: 480px; margin: 0 auto;">
                <h2>Anmeldung</h2>
                <p class="demo-login-note">Demo-Zugang: <strong>test@example.com</strong> / <strong>password</strong></p>
                <form @submit.prevent="login">
                    <label for="email">E-Mail</label>
                    <input id="email" v-model="email" type="email" required autofocus>

                    <label for="password">Passwort</label>
                    <input id="password" v-model="password" type="password" required>

                    <label class="inline-checkbox">
                        <input v-model="remember" type="checkbox">
                        Angemeldet bleiben
                    </label>

                    <button type="submit" class="btn btn-success">Einloggen</button>
                </form>
            </div>
        </main>
    </div>
</template>
