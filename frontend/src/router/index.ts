import { createRouter, createWebHistory, type RouterHistory } from 'vue-router'

import { useAuthStore } from '@/stores/auth'

declare module 'vue-router' {
  interface RouteMeta {
    requiresAuth?: boolean
    guestOnly?: boolean
  }
}

export function createAppRouter(history: RouterHistory) {
  const router = createRouter({
    history,
    routes: [
      {
        path: '/',
        name: 'habits',
        component: () => import('@/views/HabitsView.vue'),
        meta: { requiresAuth: true },
      },
      {
        path: '/login',
        name: 'login',
        component: () => import('@/views/LoginView.vue'),
        meta: { guestOnly: true },
      },
      {
        path: '/register',
        name: 'register',
        component: () => import('@/views/RegisterView.vue'),
        meta: { guestOnly: true },
      },
    ],
  })

  router.beforeEach(async (to) => {
    const auth = useAuthStore()

    if (to.meta.requiresAuth) {
      if (!auth.isAuthenticated) return { name: 'login' }

      if (!auth.user) {
        try {
          await auth.fetchCurrentUser()
        } catch (error) {
          // A 401 clears the session through the unauthorized handler; other failures keep the page.
          if (!auth.isAuthenticated) return { name: 'login' }
          console.warn('Could not load the current user', error)
        }
      }
    }

    if (to.meta.guestOnly && auth.isAuthenticated) return { name: 'habits' }
  })

  return router
}

export default createAppRouter(createWebHistory(import.meta.env.BASE_URL))
