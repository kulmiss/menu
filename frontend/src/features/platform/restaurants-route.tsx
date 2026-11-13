import { useSearch } from '@tanstack/react-router'
import { RestaurantsPage } from './restaurants'

/**
 * Restaurants screen. Filters, sort and page are read from the address by the
 * DataTable itself (?state=trial&tag=3&page=2…); `open` opens a restaurant.
 */
export default function RestaurantsRoute() {
  const { open } = useSearch({ from: '/platform/$section' })
  return <RestaurantsPage open={open} />
}
