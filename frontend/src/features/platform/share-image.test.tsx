import { fakeApi } from '@/test-utils/fake-api'
import { renderWithQuery } from '@/test-utils/render'
import { afterEach, describe, expect, it } from 'vitest'
import { userEvent } from 'vitest/browser'
import { ShareImageCard } from './share-image'

let fake: ReturnType<typeof fakeApi>

describe('Settings → Link preview', () => {
  afterEach(() => fake.restore())

  it('shows the built-in picture and uploads a new one', async () => {
    fake = fakeApi({
      'POST /platform/settings/share-image': { data: { data: {} } },
    })
    const screen = await renderWithQuery(
      <ShareImageCard
        url='https://menu.test/images/share-default.jpg'
        custom={false}
      />
    )
    await expect
      .element(
        screen.getByRole('img', { name: 'Current link preview picture' })
      )
      .toHaveAttribute('src', 'https://menu.test/images/share-default.jpg')
    await expect
      .element(screen.getByText('Using the built-in picture.'))
      .toBeVisible()
    expect(screen.container.textContent).not.toContain(
      'Use the built-in picture'
    )

    const file = new File([new Uint8Array([255, 216, 255])], 'banner.jpg', {
      type: 'image/jpeg',
    })
    await userEvent.upload(screen.getByLabelText('Choose a picture'), file)
    await expect
      .poll(() => fake.calls.find((c) => c.method === 'POST')?.url)
      .toBe('/platform/settings/share-image')
  })

  it('goes back to the built-in picture', async () => {
    fake = fakeApi({
      'DELETE /platform/settings/share-image': { data: { data: {} } },
    })
    const screen = await renderWithQuery(
      <ShareImageCard
        url='https://menu.test/media/platform/share-x.jpg'
        custom
      />
    )
    await userEvent.click(
      screen.getByRole('button', { name: 'Use the built-in picture' })
    )
    await expect
      .poll(() => fake.calls.some((c) => c.method === 'DELETE'))
      .toBe(true)
  })
})
