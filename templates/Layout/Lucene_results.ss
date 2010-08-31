
                <p>Your search for <strong>&quot;$Query&quot;</strong> found $TotalResults results.</p>

<% if Results %>
                <p>showing page $ThisPage of $TotalPages</p>

                <!-- START search results -->
                <ul>
                    <% control Results %>
                    <li>
                        <a href="$Link">$Title</a>
                        <p>$Content.LimitWordCountXML</p>
                        <a href="$Link" title="$Link">$Link</a>
                    </li>
                    <% end_control %>
                </ul>
                <!-- END search results -->

                <p>showing page $ThisPage of $TotalPages</p>

                <% if SearchPages %>
                <!-- START pagination -->
                    <ul class="pagination">
                        <% if PrevUrl = false %><% else %>
                        <li class="prev"><a href="$PrevUrl">Prev</a></li>
                        <% end_if %>               
                    <% control SearchPages %>
                        <% if IsEllipsis %>
                        <li class="ellipsis">...</li>
                        <% else %>
                            <% if Current %>
                            <li class="active"><strong>$PageNumber</strong></li>
                            <% else %>
                            <li><a href="$Link">$PageNumber</a></li>
                            <% end_if %>
                        <% end_if %>
                    <% end_control %>
                        <% if NextUrl = false %><% else %>
                        <li class="next"><a href="$NextUrl">Next</a></li>
                        <% end_if %>               
                    </ul>
                <!-- END pagination -->                
                <% end_if %>

<% else %>
                <p>No results.</p>
<% end_if %>

