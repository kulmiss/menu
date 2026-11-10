import { useNavigate, useSearch } from '@tanstack/react-router'
import { RestaurantsPage } from './restaurants'

/** Restaurants screen wired to the address (?state=…&open=…). */
export default function RestaurantsRoute() {
  const { state, open } = useSearch({ from: '/platform/$section' })
  const navigate = useNavigate({ from: '/platform/$section' })
  return (
    <RestaurantsPage
      state={state}
      open={open}
      onState={(s) => navigate({ search: { state: s } })}
    />
  )
}
