import { useNavigate, useSearch } from '@tanstack/react-router'
import { RestaurantsPage } from './restaurants'

/** Restaurants screen wired to the address (?state=…&open=…). */
export default function RestaurantsRoute() {
  const { state, open, tag } = useSearch({ from: '/platform/$section' })
  const navigate = useNavigate({ from: '/platform/$section' })
  return (
    <RestaurantsPage
      key={`tag-${tag ?? 'all'}`}
      state={state}
      open={open}
      tag={tag}
      onState={(s) => navigate({ search: { state: s } })}
    />
  )
}
