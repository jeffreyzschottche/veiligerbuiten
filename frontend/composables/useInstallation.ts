export function useInstallation() {
  const token = useState<string | null>('installation-token', () => null);

  async function ensure() {
    if (token.value) return token.value;
    const stored = localStorage.getItem('veiligbuiten-installation');
    if (stored) return token.value = stored;
    const response = await apiFetch<{ data: { token: string } }>('/installations', { method: 'POST' });
    token.value = response.data.token;
    localStorage.setItem('veiligbuiten-installation', token.value);
    return token.value;
  }

  return { ensure };
}
