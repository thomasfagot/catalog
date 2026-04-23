<template>
  <div class="app-container">
    <header v-if="user && sessionStarted" class="main-header">
      <div class="header-left">
        <img src="/logo.svg" alt="Logo" class="logo" />
        <h1 class="site-title">Catalogue</h1>
      </div>

      <div class="header-center">
        <div class="search-wrapper">
          <span class="search-icon">🔍</span>
          <input v-model="searchQuery" placeholder="Rechercher par titre ou tag..." class="search-input" />
          <button v-if="searchQuery" @click="searchQuery = ''" class="btn-reset-search" title="Réinitialiser">×</button>

          <!-- Suggestion de tags -->
          <div v-if="tagSuggestions.length > 0" class="tag-suggestions">
            <div v-for="tag in tagSuggestions" :key="tag" @click="searchQuery = tag" class="suggestion-item">
              #{{ tag }}
            </div>
          </div>
        </div>
      </div>

      <div class="header-right">
        <button @click="showAddModal = true" class="btn btn-primary btn-add">
          <span class="plus-icon">+</span> Ajouter
        </button>

        <div class="user-menu" v-click-outside="closeUserMenu">
          <button @click="userMenuOpen = !userMenuOpen" class="btn user-dropdown-trigger">
            <span class="avatar">{{ user.username.charAt(0).toUpperCase() }}</span>
            <span class="username">{{ user.username }}</span>
            <span class="chevron">▼</span>
          </button>
          <div v-if="userMenuOpen" class="dropdown-menu">
            <button @click="logout" class="btn dropdown-item danger">Déconnexion</button>
          </div>
        </div>
      </div>
    </header>

    <!-- AUTH SECTION -->
    <div v-if="!user" class="auth-wrapper">
      <div class="auth-box card">
        <div class="auth-header">
          <img src="/logo.svg" alt="Logo" class="auth-logo" />
          <h2>{{ authMode === 'login' ? 'Connexion' : 'Inscription' }}</h2>
        </div>

        <div class="auth-tabs">
          <button @click="authMode = 'login'" :class="{ active: authMode === 'login' }" class="btn">Connexion</button>
          <button @click="authMode = 'register'" :class="{ active: authMode === 'register' }" class="btn">Inscription</button>
        </div>

        <div v-if="authMode === 'login'" class="form-group">
          <input v-model="loginData.username" placeholder="Nom d'utilisateur" @keyup.enter="login" />
          <input v-model="loginData.password" type="password" placeholder="Mot de passe" @keyup.enter="login" />
          <button @click="login" :disabled="loading" class="btn btn-primary full-width">Se connecter</button>
        </div>

        <div v-else class="form-group">
          <input v-model="registerData.username" placeholder="Nom d'utilisateur" @keyup.enter="register" />
          <input v-model="registerData.password" type="password" placeholder="Mot de passe" @keyup.enter="register" />
          <button @click="register" :disabled="loading" class="btn btn-primary full-width">Créer un compte</button>
        </div>
        <p v-if="error" class="error">{{ error }}</p>
      </div>
    </div>

    <!-- SESSION (DECRYPTION) SECTION -->
    <div v-else-if="!sessionStarted" class="auth-wrapper">
      <div class="auth-box card">
        <div class="auth-header">
          <img src="/logo.svg" alt="Logo" class="auth-logo" />
          <h2>Session Sécurisée</h2>
        </div>
        <p class="session-info">Entrez votre mot de passe de décryptage pour accéder au catalogue.</p>
        <div class="form-group">
          <input v-model="decryptionPassword" autocomplete="new-password" type="password" placeholder="Mot de passe session" @keyup.enter="startSession" />
          <button @click="startSession" :disabled="loading" class="btn btn-primary full-width">Déverrouiller</button>
        </div>
        <p v-if="error" class="error">{{ error }}</p>
        <button @click="logout" class="btn btn-link mt-20">Retour à la connexion</button>
      </div>
    </div>

    <!-- VIDEOS LIST -->
    <main v-else class="content">
      <div v-if="loading && !videos.length" class="loader">
        <div class="spinner"></div>
        Chargement du catalogue...
      </div>

      <div v-if="!loading && videos.length === 0" class="empty-state">
        <p>Votre catalogue est vide.</p>
        <button @click="showAddModal = true" class="btn btn-primary">Ajouter votre premier lien</button>
      </div>

      <div class="videos-grid">
        <div v-for="video in filteredVideos" :key="video.id" class="video-card">
          <div class="video-thumb-container">
            <a :href="video.url" target="_blank" class="video-link">
              <img v-if="video.image" :src="video.image" class="video-thumb"  alt="Link thumbnail"/>
              <div v-else class="video-no-thumb">
                <span>🎬</span>
              </div>
            </a>
            <div class="video-overlay">
              <button @click="startEdit(video)" class="btn btn-icon" title="Modifier">✏️</button>
              <button @click="confirmDelete(video.id)" class="btn btn-icon btn-danger" title="Supprimer">🗑️</button>
            </div>
          </div>
          <div class="video-info">
            <h3 class="video-title" :title="video.title">
              <a :href="video.url" target="_blank" class="video-link-text">{{ video.title }}</a>
            </h3>
            <div class="video-tags">
              <span v-for="tag in video.tags" :key="tag.id" class="tag" @click="searchQuery = tag.name">{{ tag.name }}</span>
            </div>
          </div>
        </div>
      </div>
    </main>

    <!-- MODAL ADD -->
    <div v-if="showAddModal" class="modal-overlay" @click.self="closeAddModal">
      <div class="modal card">
        <div class="modal-header">
          <h3>Ajouter un lien</h3>
          <button @click="closeAddModal" class="btn btn-close">×</button>
        </div>

        <div class="form-group">
          <div class="input-with-button">
            <input v-model="newUrl" placeholder="URL (YouTube, Vimeo...)" @keyup.enter="getPreview" v-focus />
            <button @click="getPreview" :disabled="loading" class="btn btn-secondary">Récupérer</button>
          </div>

          <div v-if="previewData" class="preview-section fade-in">
            <div class="preview-row">
              <img v-if="previewData.image" :src="previewData.image" class="preview-thumb-small"  alt="Video thumbnail"/>
              <div class="preview-inputs">
                <input v-model="previewData.title" placeholder="Titre" @keyup.enter="addVideo" />
                <textarea v-model="previewData.description" placeholder="Description" rows="3"></textarea>
                <input v-model="previewTags" placeholder="Tags (séparés par des virgules)" @keyup.enter="addVideo" />
              </div>
            </div>
            <div class="modal-actions">
              <button @click="addVideo" class="btn btn-primary full-width" :disabled="loading">Enregistrer dans le catalogue</button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- MODAL DELETE -->
    <div v-if="showDeleteConfirm" class="modal-overlay" @click.self="showDeleteConfirm = false">
      <div class="modal card mini-modal">
        <h3>Supprimer ?</h3>
        <p>Cette action est irréversible.</p>
        <div class="modal-actions">
          <button @click="deleteVideo" class="btn btn-danger">Supprimer</button>
          <button @click="showDeleteConfirm = false" class="btn btn-secondary">Annuler</button>
        </div>
      </div>
    </div>

    <!-- MODAL EDIT -->
    <div v-if="editingId" class="modal-overlay" @click.self="editingId = null">
      <div class="modal card modal-large">
        <div class="modal-header">
          <h3>Modifier le lien</h3>
          <button @click="editingId = null" class="btn btn-close">×</button>
        </div>
        <div class="form-grid">
          <div class="form-left">
             <div class="edit-thumb-preview">
                <img v-if="editData.image" :src="editData.image"  alt="Link thumbnail"/>
                <div v-else class="video-no-thumb">Pas d'image</div>
             </div>
             <label>URL de l'image</label>
             <input v-model="editData.image" placeholder="Image URL" @keyup.enter="saveEdit(editingId)" />
          </div>
          <div class="form-right">
            <label>Titre</label>
            <input v-model="editData.title" placeholder="Titre" @keyup.enter="saveEdit(editingId)" />

            <label>Description</label>
            <textarea v-model="editData.description" placeholder="Description" rows="6"></textarea>

            <label>Tags (séparés par des virgules)</label>
            <input v-model="editData.tagsString" placeholder="Tags (ex: tech, video, symfony)" @keyup.enter="saveEdit(editingId)" />
          </div>
        </div>
        <div class="modal-actions">
          <button @click="saveEdit(editingId)" class="btn btn-primary" :disabled="loading">Sauvegarder</button>
          <button @click="editingId = null" class="btn btn-secondary">Annuler</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted, computed } from 'vue';

const user = ref(null);
const authMode = ref('login');
const loginData = reactive({ username: '', password: '' });
const registerData = reactive({ username: '', password: '' });

const sessionStarted = ref(false);
const decryptionPassword = ref('');
const newUrl = ref('');
const videos = ref([]);
const searchQuery = ref('');
const loading = ref(false);
const error = ref('');
const userMenuOpen = ref(false);
const showAddModal = ref(false);

// Custom directive for clicking outside
const vClickOutside = {
  mounted(el, binding) {
    el.clickOutsideEvent = (event) => {
      if (!(el === event.target || el.contains(event.target))) {
        binding.value(event);
      }
    };
    document.addEventListener('click', el.clickOutsideEvent);
  },
  unmounted(el) {
    document.removeEventListener('click', el.clickOutsideEvent);
  },
};

// Custom directive for autofocus
const vFocus = {
  mounted: (el) => el.focus()
};

const closeUserMenu = () => {
  userMenuOpen.value = false;
};

const closeAddModal = () => {
  showAddModal.value = false;
  previewData.value = null;
  newUrl.value = '';
};

const filteredVideos = computed(() => {
  if (!searchQuery.value) return videos.value;
  const q = searchQuery.value.toLowerCase();
  return videos.value.filter(v =>
    (v.title && v.title.toLowerCase().includes(q)) ||
    (v.tags && v.tags.some(t => t.name.toLowerCase().includes(q)))
  );
});

const tagSuggestions = computed(() => {
  if (!searchQuery.value || searchQuery.value.startsWith('#')) return [];
  const q = searchQuery.value.toLowerCase();
  const allTags = new Set();
  videos.value.forEach(v => {
    v.tags.forEach(t => allTags.add(t.name));
  });
  return Array.from(allTags).filter(t =>
    t.toLowerCase().includes(q) && t.toLowerCase() !== q
  ).slice(0, 5);
});

const previewData = ref(null);
const previewTags = ref('');

const editingId = ref(null);
const editData = reactive({
  title: '',
  description: '',
  image: '',
  tagsString: ''
});

const showDeleteConfirm = ref(false);
const videoToDelete = ref(null);

const checkAuth = async () => {
  try {
    const res = await fetch('/api/me');
    const data = await res.json();
    if (data.authenticated) {
      user.value = { username: data.username };
      sessionStarted.value = data.sessionStarted;
      if (sessionStarted.value) {
        loadVideos();
      }
    }
  } catch (e) {
    console.error("Auth check failed", e);
  }
};

const getCsrfToken = async () => {
  const res = await fetch('/api/csrf-token');
  const data = await res.json();
  return data.token;
};

onMounted(checkAuth);

const login = async () => {
  loading.value = true;
  error.value = '';
  try {
    const csrfToken = await getCsrfToken();
    const formData = new FormData();
    formData.append('_username', loginData.username);
    formData.append('_password', loginData.password);
    formData.append('_csrf_token', csrfToken);

    const res = await fetch('/api/login', {
      method: 'POST',
      body: formData
    });

    if (res.ok) {
      const data = await res.json();
      user.value = { username: data.user };
      await checkAuth();
    } else {
      let data;
      try { data = await res.json(); } catch (e) { data = { error: 'Erreur de connexion' }; }
      error.value = data.error || 'Identifiants invalides';
    }
  } catch (e) {
    error.value = "Erreur réseau";
  } finally {
    loading.value = false;
  }
};

const register = async () => {
  loading.value = true;
  error.value = '';
  try {
    const csrfToken = await getCsrfToken();
    const res = await fetch('/api/register', {
      method: 'POST',
      body: JSON.stringify({
        ...registerData,
        _csrf_token: csrfToken
      }),
      headers: { 'Content-Type': 'application/json' }
    });
    if (res.ok) {
      await checkAuth();
    } else {
      const data = await res.json();
      error.value = data.error || "Erreur lors de l'inscription";
    }
  } catch (e) {
    error.value = "Erreur réseau";
  } finally {
    loading.value = false;
  }
};

const logout = async () => {
  await fetch('/api/logout');
  user.value = null;
  sessionStarted.value = false;
  videos.value = [];
  userMenuOpen.value = false;
};

const startSession = async () => {
  if (!decryptionPassword.value) return;
  loading.value = true;
  error.value = '';
  try {
    const res = await fetch('/api/session', {
      method: 'POST',
      body: JSON.stringify({ password: decryptionPassword.value }),
      headers: { 'Content-Type': 'application/json' }
    });
    if (res.ok) {
      sessionStarted.value = true;
      loadVideos();
    } else {
      error.value = 'Mot de passe session invalide';
    }
  } catch (e) {
    error.value = 'Erreur lors de l\'initialisation';
  } finally {
    loading.value = false;
  }
};

const loadVideos = async () => {
  loading.value = true;
  try {
    const res = await fetch('/api/videos');
    if (res.ok) {
      videos.value = await res.json();
    }
  } finally {
    loading.value = false;
  }
};

const getPreview = async () => {
  if (!newUrl.value) return;
  loading.value = true;
  try {
    const res = await fetch('/api/videos/preview', {
      method: 'POST',
      body: JSON.stringify({ url: newUrl.value }),
      headers: { 'Content-Type': 'application/json' }
    });
    if (res.ok) {
      previewData.value = await res.json();
    }
  } finally {
    loading.value = false;
  }
};

const addVideo = async () => {
  loading.value = true;
  try {
    const res = await fetch('/api/videos', {
      method: 'POST',
      body: JSON.stringify({
        ...previewData.value,
        url: newUrl.value,
        tags: previewTags.value.split(',').map(s => s.trim()).filter(s => s)
      }),
      headers: { 'Content-Type': 'application/json' }
    });
    if (res.ok) {
      closeAddModal();
      loadVideos();
    }
  } finally {
    loading.value = false;
  }
};

const startEdit = (video) => {
  editingId.value = video.id;
  editData.title = video.title;
  editData.description = video.description;
  editData.image = video.image;
  editData.tagsString = video.tags.map(t => t.name).join(', ');
};

const saveEdit = async (id) => {
  loading.value = true;
  try {
    const res = await fetch(`/api/videos/${id}`, {
      method: 'PUT',
      body: JSON.stringify({
        ...editData,
        tags: editData.tagsString.split(',').map(s => s.trim()).filter(s => s)
      }),
      headers: { 'Content-Type': 'application/json' }
    });
    if (res.ok) {
      editingId.value = null;
      loadVideos();
    }
  } finally {
    loading.value = false;
  }
};

const confirmDelete = (id) => {
  videoToDelete.value = id;
  showDeleteConfirm.value = true;
};

const deleteVideo = async () => {
  if (!videoToDelete.value) return;
  loading.value = true;
  try {
    const res = await fetch(`/api/videos/${videoToDelete.value}`, {
      method: 'DELETE'
    });
    if (res.ok) {
      showDeleteConfirm.value = false;
      loadVideos();
    }
  } finally {
    loading.value = false;
  }
};
</script>

<style>
:root {
  --primary: #6366f1;
  --primary-hover: #4f46e5;
  --bg: #0f172a;
  --header-bg: #1e293b;
  --card-bg: #1e293b;
  --text: #f8fafc;
  --text-muted: #94a3b8;
  --border: #334155;
  --input-bg: #0f172a;
  --danger: #ef4444;
  --success: #10b981;
}

body {
  margin: 0;
  font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
  background-color: var(--bg);
  color: var(--text);
  overflow-x: hidden;
}

.app-container {
  min-height: 100vh;
  display: flex;
  flex-direction: column;
}

/* HEADER */
.main-header {
  height: 64px;
  background: var(--header-bg);
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0 24px;
  border-bottom: 1px solid var(--border);
  position: sticky;
  top: 0;
  z-index: 100;
}

.header-left {
  display: flex;
  align-items: center;
  gap: 12px;
}

.logo { width: 32px; height: 32px; }
.site-title { font-size: 1.25rem; font-weight: 700; margin: 0; }

.header-center {
  flex: 1;
  max-width: 600px;
  margin: 0 20px;
}

.search-wrapper {
  position: relative;
  display: flex;
  align-items: center;
}

.search-icon {
  position: absolute;
  left: 12px;
  color: var(--text-muted);
}

.search-input {
  background: var(--input-bg) !important;
  border-color: var(--border) !important;
  padding-left: 40px !important;
  padding-right: 40px !important;
  color: white !important;
}

.btn-reset-search {
  position: absolute;
  right: 12px;
  background: none;
  border: none;
  color: var(--text-muted);
  font-size: 1.2rem;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  width: 24px;
  height: 24px;
  border-radius: 50%;
}

.btn-reset-search:hover {
  background: rgba(255,255,255,0.1);
  color: var(--text);
}

.tag-suggestions {
  position: absolute;
  top: 100%;
  left: 0;
  right: 0;
  background: var(--card-bg);
  border: 1px solid var(--border);
  border-radius: 8px;
  margin-top: 4px;
  box-shadow: 0 10px 15px -3px rgba(0,0,0,0.5);
  z-index: 1000;
  overflow: hidden;
}

.suggestion-item {
  padding: 8px 12px;
  cursor: pointer;
  font-size: 0.9rem;
  color: var(--text-muted);
  transition: all 0.2s;
}

.suggestion-item:hover {
  background: var(--primary);
  color: white;
}

.header-right {
  display: flex;
  align-items: center;
  gap: 16px;
}

.btn-add {
  display: flex;
  align-items: center;
  gap: 8px;
  font-weight: 600;
}

.plus-icon { font-size: 1.2rem; }

/* USER MENU */
.user-menu {
  position: relative;
}

.user-dropdown-trigger {
  background: none;
  border: none;
  display: flex;
  align-items: center;
  gap: 8px;
  cursor: pointer;
  color: var(--text);
  padding: 4px 8px;
  border-radius: 8px;
  transition: background 0.2s;
}

.user-dropdown-trigger:hover { background: rgba(255,255,255,0.05); }

.avatar {
  width: 32px;
  height: 32px;
  background: var(--primary);
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 700;
}

.username { font-weight: 500; font-size: 0.9rem; }
.chevron { font-size: 0.7rem; color: var(--text-muted); }

.dropdown-menu {
  position: absolute;
  top: 110%;
  right: 0;
  background: var(--card-bg);
  border: 1px solid var(--border);
  border-radius: 8px;
  box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.5);
  min-width: 160px;
  overflow: hidden;
}

.dropdown-item {
  width: 100%;
  text-align: left;
  background: none;
  border: none;
  color: var(--text);
  cursor: pointer;
  font-size: 0.9rem;
}

.dropdown-item:hover { background: rgba(255,255,255,0.05); }
.dropdown-item.danger { color: var(--danger); }

/* AUTH */
.auth-wrapper {
  flex: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 20px;
}

.auth-box {
  width: 100%;
  max-width: 400px;
  padding: 32px !important;
}

.auth-header {
  text-align: center;
  margin-bottom: 24px;
}

.auth-logo { width: 48px; height: 48px; margin-bottom: 16px; }

.auth-tabs {
  display: flex;
  gap: 8px;
  margin-bottom: 24px;
  background: var(--input-bg);
  padding: 4px;
  border-radius: 8px;
}

.auth-tabs button {
  flex: 1;
  padding: 8px;
  background: none;
  border: none;
  color: var(--text-muted);
  border-radius: 6px;
  font-weight: 600;
}

.auth-tabs button.active {
  background: var(--header-bg);
  color: var(--primary);
  box-shadow: 0 1px 2px rgba(0,0,0,0.2);
}

.session-info {
  text-align: center;
  color: var(--text-muted);
  font-size: 0.9rem;
  margin-bottom: 20px;
}

/* CONTENT */
.content {
  flex: 1;
  padding: 24px;
  width: 100%;
  box-sizing: border-box;
}

.videos-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
  gap: 24px;
}

.video-card {
  display: flex;
  flex-direction: column;
  background: var(--card-bg);
  border-radius: 12px;
  overflow: hidden;
  border: 1px solid var(--border);
  transition: transform 0.2s, box-shadow 0.2s;
}

.video-card:hover {
  transform: translateY(-4px);
  box-shadow: 0 10px 15px -3px rgba(0,0,0,0.3);
}

.video-thumb-container {
  position: relative;
  aspect-ratio: 16/9;
  width: 100%;
  background: #0f172a;
}

.video-link {
  display: block;
  width: 100%;
  height: 100%;
}

.video-link-text {
  color: inherit;
  text-decoration: none;
  transition: color 0.2s;
}

.video-link-text:hover {
  color: var(--primary);
}

.video-thumb {
  width: 100%;
  height: 100%;
  object-fit: cover;
  transition: transform 0.3s ease;
}

.video-thumb-container:hover .video-thumb {
  transform: scale(1.05);
}
.video-no-thumb {
  height: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 3rem;
  opacity: 0.3;
}

.video-overlay {
  position: absolute;
  top: 0; left: 0; right: 0; bottom: 0;
  background: rgba(0,0,0,0.6);
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 12px;
  opacity: 0;
  transition: opacity 0.2s;
}

.video-card:hover .video-overlay { opacity: 1; }

.video-info { padding: 12px; }
.video-title {
  margin: 0;
  font-size: 0.95rem;
  font-weight: 600;
  line-height: 1.4;
  height: 2.8em;
  overflow: hidden;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
}

.video-tags {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  margin-top: 10px;
}

.tag {
  font-size: 0.75rem;
  background: rgba(99, 102, 241, 0.15);
  color: #a5b4fc;
  padding: 2px 10px;
  border-radius: 99px;
  cursor: pointer;
  border: 1px solid rgba(99, 102, 241, 0.2);
}

.tag:hover { background: var(--primary); color: white; }

/* MODALS */
.modal-overlay {
  position: fixed;
  top: 0; left: 0; right: 0; bottom: 0;
  background: rgba(0,0,0,0.75);
  backdrop-filter: blur(4px);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
  padding: 20px;
}

.modal {
  width: 100%;
  max-width: 500px;
  background: var(--card-bg);
  border: 1px solid var(--border);
  border-radius: 16px;
  padding: 0 !important;
  overflow: hidden;
}

.modal-header {
  padding: 16px 24px;
  border-bottom: 1px solid var(--border);
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.modal-header h3 { margin: 0; font-size: 1.1rem; }

.btn-close {
  cursor: pointer;
}

.form-group { padding: 24px; display: flex; flex-direction: column; gap: 16px; }

.input-with-button { display: flex; gap: 10px; }

.preview-section {
  background: var(--bg);
  border-radius: 12px;
  padding: 16px;
  margin-top: 8px;
}

.preview-row { display: flex; gap: 16px; }
.preview-thumb-small { width: 80px; height: 80px; border-radius: 8px; object-fit: cover; }
.preview-inputs { flex: 1; display: flex; flex-direction: column; gap: 8px; }

.modal-large { max-width: 800px; }
.form-grid { display: grid; grid-template-columns: 250px 1fr; gap: 24px; padding: 24px; }
.edit-thumb-preview {
  width: 100%;
  aspect-ratio: 16/9;
  background: var(--bg);
  border-radius: 12px;
  overflow: hidden;
  margin-bottom: 12px;
}
.edit-thumb-preview img { width: 100%; height: 100%; object-fit: cover; }

.modal-actions {
  padding: 16px 24px;
  background: rgba(0,0,0,0.1);
  display: flex;
  justify-content: flex-end;
  gap: 12px;
}

.mini-modal { max-width: 350px; text-align: center; }
.mini-modal .modal-actions { justify-content: center; }

/* UTILS */
.btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 8px 16px;
  border-radius: 8px;
  font-size: 0.9rem;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s;
  border: none;
  font-family: inherit;
  gap: 8px;
}

.btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.btn-primary { background: var(--primary); color: white; }
.btn-primary:hover { background: var(--primary-hover); }

.btn-secondary { background: #334155; color: white; }
.btn-secondary:hover { background: #475569; }

.btn-danger { background: var(--danger); color: white; }
.btn-danger:hover { background: #dc2626; }

.btn-icon {
  padding: 8px;
  width: 36px;
  height: 36px;
  border-radius: 8px;
  background: rgba(255,255,255,0.1);
  color: white;
}
.btn-icon:hover { background: rgba(255,255,255,0.2); transform: scale(1.1); }

.btn-link {
  background: none;
  color: var(--primary);
  padding: 0;
  font-weight: 600;
}
.btn-link:hover { text-decoration: underline; }

.btn-close {
  background: none;
  border: none;
  color: var(--text-muted);
  font-size: 1.5rem;
  padding: 4px;
  width: 32px;
  height: 32px;
  display: flex;
  align-items: center;
  justify-content: center;
}
.btn-close:hover { color: var(--text); background: rgba(255,255,255,0.1); }

.full-width { width: 100%; }
.mt-20 { margin-top: 20px; }

button {
  cursor: pointer;
}

input, textarea {
  background: var(--input-bg);
  border: 1px solid var(--border);
  border-radius: 8px;
  padding: 10px 12px;
  color: var(--text);
  font-size: 0.95rem;
  width: 100%;
  box-sizing: border-box;
}

input:focus, textarea:focus {
  border-color: var(--primary);
  outline: none;
  box-shadow: 0 0 0 2px rgba(99, 102, 241, 0.2);
}

label { font-size: 0.8rem; font-weight: 600; color: var(--text-muted); margin-bottom: -10px; }

.error { color: var(--danger); font-size: 0.85rem; margin-top: 4px; text-align: center; }

.loader {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 16px;
  padding: 100px 0;
  color: var(--text-muted);
}

.spinner {
  width: 40px;
  height: 40px;
  border: 4px solid var(--border);
  border-top-color: var(--primary);
  border-radius: 50%;
  animation: spin 1s linear infinite;
}

@keyframes spin { to { transform: rotate(360deg); } }

.fade-in { animation: fadeIn 0.3s ease-in-out; }
@keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }

.empty-state {
  text-align: center;
  padding: 100px 0;
  color: var(--text-muted);
}
</style>
