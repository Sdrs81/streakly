import { describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'

import type { HabitPayload } from '@/types/api'
import HabitForm from '../HabitForm.vue'

function buildSave() {
  return vi.fn<(payload: HabitPayload) => Promise<void>>().mockResolvedValue(undefined)
}

describe('HabitForm', () => {
  it('sends the typed values and clears the fields after creating', async () => {
    const save = buildSave()
    const wrapper = mount(HabitForm, { props: { submitLabel: 'Add habit', save } })

    await wrapper.get('input[name=name]').setValue('Read')
    await wrapper.get('textarea[name=description]').setValue('Twenty pages')
    await wrapper.get('form').trigger('submit')
    await flushPromises()

    expect(save).toHaveBeenCalledWith({ name: 'Read', description: 'Twenty pages' })
    expect((wrapper.get('input[name=name]').element as HTMLInputElement).value).toBe('')
    expect(wrapper.emitted('saved')).toHaveLength(1)
  })

  it('keeps the values when editing an existing habit', async () => {
    const save = buildSave()
    const wrapper = mount(HabitForm, {
      props: {
        submitLabel: 'Save',
        initialValues: { name: 'Read', description: null },
        save,
      },
    })

    await wrapper.get('form').trigger('submit')
    await flushPromises()

    expect((wrapper.get('input[name=name]').element as HTMLInputElement).value).toBe('Read')
  })

  it('sends a blank description as null', async () => {
    const save = buildSave()
    const wrapper = mount(HabitForm, { props: { submitLabel: 'Add habit', save } })

    await wrapper.get('input[name=name]').setValue('Read')
    await wrapper.get('form').trigger('submit')
    await flushPromises()

    expect(save).toHaveBeenCalledWith({ name: 'Read', description: null })
  })
})
