# Integrasi Google Authentication dengan Nuxt

Dokumen ini menjelaskan integrasi Google Authentication antara:

- Backend Laravel: `https://api.sewantara.id`
- Frontend Nuxt: `https://app.sewantara.id`

## Alur autentikasi

```text
Nuxt
  → GET https://api.sewantara.id/api/central/auth/google/redirect
  → Google OAuth
  → GET https://api.sewantara.id/api/central/auth/google/callback
  → https://app.sewantara.id/auth/google/callback?code=...
  → POST https://api.sewantara.id/api/central/auth/google/exchange
  → Bearer token dan data pengguna
  → Dashboard Nuxt
```

Google harus mengembalikan authorization code ke backend. Setelah autentikasi
selesai, backend membuat kode exchange sekali pakai dan mengarahkan browser ke
Nuxt. Nuxt kemudian menukar kode tersebut dengan bearer token.

Kode exchange berlaku selama 60 detik dan hanya dapat digunakan satu kali.

## Konfigurasi environment Nuxt

Tambahkan konfigurasi berikut pada environment production Nuxt:

```env
NUXT_PUBLIC_API_BASE=https://api.sewantara.id
```

Daftarkan nilainya pada `nuxt.config.ts`:

```ts
export default defineNuxtConfig({
  runtimeConfig: {
    public: {
      apiBase: process.env.NUXT_PUBLIC_API_BASE,
    },
  },
})
```

## Tombol login Google

Login Google harus dimulai menggunakan browser navigation, bukan `$fetch`,
karena pengguna harus berpindah ke halaman autentikasi Google.

```vue
<script setup lang="ts">
const config = useRuntimeConfig()

function loginWithGoogle() {
  window.location.assign(
    `${config.public.apiBase}/api/central/auth/google/redirect?device_name=nuxt-web`,
  )
}
</script>

<template>
  <button type="button" @click="loginWithGoogle">
    Masuk dengan Google
  </button>
</template>
```

Parameter `device_name` bersifat opsional dan digunakan sebagai nama token
Sanctum yang diterbitkan backend.

## Halaman callback Nuxt

Buat file `pages/auth/google/callback.vue`:

```vue
<script setup lang="ts">
interface GoogleAuthResponse {
  success: boolean
  data: {
    token_type: 'Bearer'
    access_token: string
    user: {
      id: number
      tenant_id: string
      name: string
      email: string
    }
  }
}

const route = useRoute()
const config = useRuntimeConfig()

const loading = ref(true)
const errorMessage = ref<string | null>(null)

onMounted(async () => {
  const code = Array.isArray(route.query.code)
    ? route.query.code[0]
    : route.query.code

  if (!code) {
    errorMessage.value = 'Kode autentikasi Google tidak ditemukan.'
    loading.value = false
    return
  }

  try {
    const response = await $fetch<GoogleAuthResponse>(
      `${config.public.apiBase}/api/central/auth/google/exchange`,
      {
        method: 'POST',
        body: { code },
      },
    )

    // Sesuaikan penyimpanan token dengan auth store aplikasi.
    const accessToken = useCookie<string | null>('access_token', {
      sameSite: 'lax',
      secure: true,
    })

    accessToken.value = response.data.access_token

    await navigateTo('/dashboard', { replace: true })
  } catch (error) {
    errorMessage.value =
      'Login Google gagal atau kode autentikasi sudah kedaluwarsa.'
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <main>
    <p v-if="loading">Memproses login Google...</p>

    <div v-else-if="errorMessage">
      <p>{{ errorMessage }}</p>
      <NuxtLink to="/login">Kembali ke halaman login</NuxtLink>
    </div>
  </main>
</template>
```

Jika aplikasi sudah memiliki Pinia auth store, simpan token dan data pengguna
melalui store tersebut sebagai pengganti contoh `useCookie` di atas.

## Endpoint backend

### Memulai autentikasi

```http
GET /api/central/auth/google/redirect?device_name=nuxt-web
```

Endpoint ini mengarahkan browser ke halaman autentikasi Google.

### Callback Google

```http
GET /api/central/auth/google/callback
```

Endpoint ini dipanggil oleh Google. Setelah autentikasi berhasil, backend
mengarahkan browser ke:

```text
https://app.sewantara.id/auth/google/callback?code=ONE_TIME_CODE
```

### Menukar kode autentikasi

```http
POST /api/central/auth/google/exchange
Content-Type: application/json

{
  "code": "ONE_TIME_CODE"
}
```

Contoh respons berhasil:

```json
{
  "success": true,
  "message": "Berhasil masuk dengan Google.",
  "data": {
    "token_type": "Bearer",
    "access_token": "1|sanctum-token",
    "user": {
      "id": 1,
      "tenant_id": "tenant-id",
      "name": "Nama Pengguna",
      "email": "user@example.com"
    }
  }
}
```

Contoh kode yang sudah kedaluwarsa atau pernah digunakan:

```json
{
  "success": false,
  "error": {
    "code": "GOOGLE_AUTH_CODE_INVALID",
    "message": "Kode autentikasi Google tidak valid atau sudah kedaluwarsa.",
    "details": null
  }
}
```

## Konfigurasi Google Cloud Console

Pada OAuth Client ID bertipe **Web application**, tambahkan Authorized redirect
URI berikut secara persis:

```text
https://api.sewantara.id/api/central/auth/google/callback
```

URL berikut bukan Authorized redirect URI Google:

```text
https://api.sewantara.id/api/central/auth/google/redirect
https://app.sewantara.id/auth/google/callback
```

Endpoint `/redirect` hanya digunakan Nuxt untuk memulai autentikasi. Callback
Nuxt dipanggil oleh backend setelah backend menyelesaikan proses OAuth.

## Konfigurasi backend production

```env
GOOGLE_CLIENT_ID=your-google-client-id
GOOGLE_CLIENT_SECRET=your-google-client-secret
GOOGLE_REDIRECT_URI=https://api.sewantara.id/api/central/auth/google/callback
GOOGLE_AUTH_FRONTEND_CALLBACK_URL=https://app.sewantara.id/auth/google/callback
GOOGLE_AUTH_EXCHANGE_TTL=60
```

Setelah mengubah environment backend, bersihkan dan buat ulang config cache:

```bash
php artisan optimize:clear
php artisan config:cache
```

## Checklist deployment

- OAuth Client dibuat dengan tipe **Web application**.
- Authorized redirect URI Google mengarah ke callback backend.
- `GOOGLE_REDIRECT_URI` menggunakan HTTPS dan tidak memiliki trailing slash.
- `GOOGLE_AUTH_FRONTEND_CALLBACK_URL` mengarah ke halaman callback Nuxt.
- Halaman `pages/auth/google/callback.vue` tersedia di frontend.
- Origin `https://app.sewantara.id` diizinkan oleh konfigurasi CORS backend.
- Config cache backend dibuat ulang setelah environment diperbarui.
- Nuxt berhasil menukar kode sebelum masa berlaku 60 detik berakhir.
- Kode exchange tidak digunakan lebih dari satu kali.
