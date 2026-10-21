import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../models/models.dart';
import '../services/api_client.dart';
import '../theme/tokens.dart';
import '../widgets/lk_widgets.dart';

/// The knowledge base: categories, search and article list.
class KnowledgebaseScreen extends StatefulWidget {
  const KnowledgebaseScreen({super.key});

  @override
  State<KnowledgebaseScreen> createState() => _KnowledgebaseScreenState();
}

class _KnowledgebaseScreenState extends State<KnowledgebaseScreen> {
  final _searchController = TextEditingController();

  List<LkCategory> _categories = const [];
  List<LkArticle> _articles = const [];
  LkArticle? _selected;
  int? _selectedCategoryId;
  String? _error;
  bool _loading = true;

  @override
  void initState() {
    super.initState();
    _load();
  }

  @override
  void dispose() {
    _searchController.dispose();
    super.dispose();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });

    try {
      final api = context.read<LkApiClient>();
      final categories = await api.knowledgebaseCategories();
      final articles = await api.knowledgebase(
        categoryId: _selectedCategoryId,
        search: _searchController.text.trim(),
      );
      if (!mounted) return;
      setState(() {
        _categories = categories.map(LkCategory.fromJson).toList();
        _articles = articles.map(LkArticle.fromJson).toList();
        _loading = false;
      });
    } on LkApiException catch (e) {
      if (!mounted) return;
      setState(() {
        _error = e.message;
        _loading = false;
      });
    } on LkNetworkException catch (e) {
      if (!mounted) return;
      setState(() {
        _error = e.message;
        _loading = false;
      });
    }
  }

  void _selectCategory(int? id) {
    setState(() => _selectedCategoryId = id);
    _load();
  }

  @override
  Widget build(BuildContext context) {
    if (_loading) return const LkLoading();
    if (_error != null) return LkErrorView(message: _error!, onRetry: _load);

    if (_selected != null) {
      final article = _selected!;
      return ListView(
        children: [
          Align(
            alignment: Alignment.centerLeft,
            child: TextButton.icon(
              onPressed: () => setState(() => _selected = null),
              icon: const Icon(Icons.arrow_back),
              label: const Text('Voltar'),
            ),
          ),
          LkCard(
            title: article.title,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const SizedBox(height: 4),
                Text(
                  'Categoria: ${article.categoryTitle}  •  Atualizado: ${article.lastUpdated}',
                  style: const TextStyle(color: LkColors.textLight, fontSize: 12),
                ),
                const SizedBox(height: 12),
                Text(article.content),
              ],
            ),
          ),
        ],
      );
    }

    return ListView(
      children: [
        Row(
          children: [
            Expanded(
              child: TextField(
                key: const Key('kb-search'),
                controller: _searchController,
                decoration: const InputDecoration(
                  labelText: 'Buscar artigos',
                  prefixIcon: Icon(Icons.search),
                ),
                onSubmitted: (_) => _load(),
              ),
            ),
            const SizedBox(width: 8),
            ElevatedButton(onPressed: _load, child: const Text('Buscar')),
          ],
        ),
        const SizedBox(height: 16),
        const LkSectionTitle('Categorias'),
        Wrap(
          spacing: 8,
          runSpacing: 8,
          children: [
            ChoiceChip(
              label: const Text('Todos'),
              selected: _selectedCategoryId == null,
              selectedColor: LkColors.primary,
              onSelected: (_) => _selectCategory(null),
            ),
            for (final category in _categories)
              ChoiceChip(
                label: Text('${category.title} (${category.articleCount})'),
                selected: _selectedCategoryId == category.id,
                selectedColor: LkColors.primary,
                onSelected: (_) => _selectCategory(category.id),
              ),
          ],
        ),
        const SizedBox(height: 16),
        const LkSectionTitle('Artigos'),
        if (_articles.isEmpty)
          const LkEmptyState(
            message: 'Nenhum artigo encontrado.',
            icon: Icons.menu_book_outlined,
          )
        else
          for (final article in _articles) ...[
            LkCard(
              child: InkWell(
                onTap: () => setState(() => _selected = article),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      article.title,
                      style: const TextStyle(
                        fontWeight: FontWeight.w600,
                        color: LkColors.text,
                      ),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      article.description,
                      style: const TextStyle(color: LkColors.textLight),
                    ),
                  ],
                ),
              ),
            ),
            const SizedBox(height: 8),
          ],
      ],
    );
  }
}
